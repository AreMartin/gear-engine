/**
 * Triage config tests. Run with: node tests/triage/triage-configs.test.js
 *
 * 1. Every config is structurally sound (rules point at real diagnoses, option ids exist).
 * 2. Declarative rules give the same diagnosis as the legacy prototype function for
 *    every answer combination the UI can produce (rygg).
 */
const assert = require( 'assert' );
const fs = require( 'fs' );
const path = require( 'path' );
const Rules = require( '../../modules/triage/assets/js/triage-rules.js' );

const configsDir = path.join( __dirname, '../../modules/triage/configs' );
let failures = 0;

function check( name, fn ) {
	try {
		fn();
		console.log( 'PASS ' + name );
	} catch ( e ) {
		failures++;
		console.log( 'FAIL ' + name + '\n     ' + e.message );
	}
}

function collectFields( cond, out ) {
	if ( cond.all ) { cond.all.forEach( ( c ) => collectFields( c, out ) ); }
	else if ( cond.any ) { cond.any.forEach( ( c ) => collectFields( c, out ) ); }
	else if ( cond.not ) { collectFields( cond.not, out ); }
	else { out.push( cond ); }
	return out;
}

fs.readdirSync( configsDir ).filter( ( f ) => f.endsWith( '.json' ) ).forEach( ( file ) => {
	const config = JSON.parse( fs.readFileSync( path.join( configsDir, file ), 'utf8' ) );

	check( file + ': structure', () => {
		assert( config.diagnoses[ config.defaultDiagnosis ], 'default diagnosis missing' );
		const stepIds = config.steps.map( ( s ) => s.id );
		assert.strictEqual( new Set( stepIds ).size, stepIds.length, 'duplicate step ids' );
		config.rules.forEach( ( rule ) => {
			assert( config.diagnoses[ rule.diagnosis ], 'rule targets unknown diagnosis ' + rule.diagnosis );
			collectFields( rule.when, [] ).forEach( ( c ) => {
				const step = config.steps.find( ( s ) => s.id === c.field );
				assert( step, 'rule uses unknown field ' + c.field );
				if ( step.options && c.value !== undefined ) {
					[].concat( c.value ).forEach( ( v ) => {
						assert( step.options.some( ( o ) => o.id === v ), 'unknown option ' + v + ' for ' + c.field );
					} );
				}
			} );
		} );
		Object.keys( config.defaultAnswers ).forEach( ( k ) => {
			assert( stepIds.includes( k ), 'defaultAnswers key without step: ' + k );
		} );
		Object.entries( config.diagnoses ).forEach( ( [ id, d ] ) => {
			[ 'title', 'category', 'severity', 'patientDesc', 'clinicalHypothesis', 'icpc2', 'icd10' ].forEach( ( k ) => {
				assert( d[ k ], id + ' missing ' + k );
			} );
		} );
	} );
} );

/**
 * Walks the cartesian product of answers the UI can produce.
 * Singles: null plus every option. Multis: [], exclusive none, and every subset of real options
 * (or the given `multiOverride` values). Steps listed in `ignore` stay at their defaults.
 */
function walkAnswers( config, ignore, multiOverride, callback ) {
	const isNone = ( o ) => o === 'ingen' || o === 'none';
	const values = ( step ) => {
		if ( ignore.includes( step.id ) ) { return [ config.defaultAnswers[ step.id ] ]; }
		if ( step.type === 'slider' ) { return [ 5 ]; }
		if ( step.type === 'single' ) { return [ null, ...step.options.map( ( o ) => o.id ) ]; }
		if ( multiOverride[ step.id ] ) { return multiOverride[ step.id ]; }
		const real = step.options.map( ( o ) => o.id ).filter( ( o ) => ! isNone( o ) );
		const none = step.options.map( ( o ) => o.id ).find( isNone );
		const sets = [ [], [ none ] ];
		for ( let mask = 1; mask < 1 << real.length; mask++ ) {
			sets.push( real.filter( ( _, i ) => mask & ( 1 << i ) ) );
		}
		return sets;
	};

	let count = 0;
	const walk = ( i, answers ) => {
		if ( i === config.steps.length ) {
			callback( answers );
			count++;
			return;
		}
		values( config.steps[ i ] ).forEach( ( v ) => walk( i + 1, Object.assign( {}, answers, { [ config.steps[ i ].id ]: v } ) ) );
	};
	walk( 0, {} );
	return count;
}

function loadConfig( region ) {
	return JSON.parse( fs.readFileSync( path.join( configsDir, region + '.json' ), 'utf8' ) );
}

function compareWithLegacy( region, legacy, ignore, multiOverride ) {
	const config = loadConfig( region );
	const count = walkAnswers( config, ignore, multiOverride, ( answers ) => {
		assert.strictEqual(
			Rules.resolveDiagnosis( config, answers ),
			legacy( answers ),
			'mismatch for ' + JSON.stringify( answers )
		);
	} );
	console.log( '     compared ' + count + ' combinations' );
}

check( 'rygg: JSON rules match legacy function for all reachable answers', () => {
	compareWithLegacy( 'rygg', require( './fixtures/legacy-rygg.js' ), [], {} );
} );

check( 'skulder: JSON rules match legacy function for all reachable answers', () => {
	compareWithLegacy( 'skulder', require( './fixtures/legacy-skulder.js' ), [ 'duration' ], {
		red_flags: [ [], [ 'none' ], [ 'fever' ], [ 'fever', 'deformity' ] ],
	} );
} );

// Every diagnosis must be reachable, otherwise a rule is dead or shadowed by an earlier one.
fs.readdirSync( configsDir ).filter( ( f ) => f.endsWith( '.json' ) ).forEach( ( file ) => {
	const region = file.replace( '.json', '' );
	check( region + ': every diagnosis is reachable', () => {
		const config = loadConfig( region );
		const seen = new Set();
		walkAnswers( config, [], {}, ( answers ) => seen.add( Rules.resolveDiagnosis( config, answers ) ) );
		const unreachable = Object.keys( config.diagnoses ).filter( ( id ) => ! seen.has( id ) );
		assert.deepStrictEqual( unreachable, [], 'unreachable diagnoses: ' + unreachable.join( ', ' ) );
	} );
} );

check( 'rules: operators', () => {
	const a = { x: 'a', list: [ 'p', 'q' ], none: [] };
	assert( Rules.matches( { field: 'x', op: 'eq', value: 'a' }, a ) );
	assert( Rules.matches( { field: 'x', op: 'in', value: [ 'a', 'b' ] }, a ) );
	assert( Rules.matches( { field: 'x', op: 'notIn', value: [ 'b' ] }, a ) );
	assert( Rules.matches( { field: 'list', op: 'includes', value: 'q' }, a ) );
	assert( Rules.matches( { field: 'missing', op: 'notIncludes', value: 'q' }, a ) );
	assert( Rules.matches( { field: 'none', op: 'empty' }, a ) );
	assert( Rules.matches( { any: [ { field: 'x', op: 'eq', value: 'z' }, { not: { field: 'x', op: 'eq', value: 'z' } } ] }, a ) );
	assert( ! Rules.matches( { field: 'x', op: 'bogus' }, a ) );
} );

process.exit( failures ? 1 : 0 );
