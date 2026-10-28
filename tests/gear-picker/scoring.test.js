/**
 * Gear picker scoring tests. Run with: node tests/gear-picker/scoring.test.js
 */
const assert = require( 'assert' );
const Scoring = require( '../../modules/gear-picker/assets/js/gear-picker-scoring.js' );
const config = require( '../../modules/gear-picker/configs/treadmill.json' );

const BASE = { speed: 16, weight: 120, incline: null, motor_incline: false, foldable: false, app: false, screen: false, cushion: false, rehab: false };
const item = ( id, price, specs, extra = {} ) => Object.assign( { id, name: 'M' + id, shop: 'Annen', price, old: 0, specs: Object.assign( {}, BASE, specs ) }, extra );
const answers = ( over = {} ) => Object.assign( { goal: 'walk', freq: 'light', cushion: 'standard', space: 'any', incline: 'no', tech: 'none', w: 90, b: 35000 }, over );
const ids = ( r ) => r.items.map( ( p ) => p.id );

let n = 0;
const test = ( name, fn ) => { fn(); n++; console.log( '[PASS] ' + name ); };

const items = [
	item( 1, 9000, { speed: 16, weight: 130, foldable: true } ),
	item( 2, 15000, { speed: 20, weight: 150, cushion: true, motor_incline: true, app: true } ),
	item( 3, 30000, { speed: 22, weight: 200, screen: true, app: true, rehab: true, cushion: true } ),
	item( 4, 4000, { speed: 12, weight: 90 } ),
	item( 5, 3000, { speed: 16, weight: 120 } ),
];

test( 'defaults come from slider steps', () => {
	assert.deepStrictEqual( Scoring.defaults( config ), { w: 90, b: 35000 } );
} );

test( 'budget: price cap, and a lower bound of 30% above 15000', () => {
	assert.deepStrictEqual( ids( Scoring.rank( config, items, answers( { b: 10000 } ) ) ).sort(), [ 1, 4, 5 ] );
	const r = Scoring.rank( config, items, answers( { b: 35000 } ) );
	assert.ok( ! ids( r ).includes( 4 ) && ! ids( r ).includes( 5 ) );
} );

test( 'weight must cover user weight, with extra buffer for frequent use', () => {
	assert.ok( ids( Scoring.rank( config, items, answers( { w: 130, b: 20000 } ) ) ).includes( 1 ) );
	assert.ok( ! ids( Scoring.rank( config, items, answers( { w: 130, freq: 'active', b: 20000 } ) ) ).includes( 1 ) );
} );

test( 'heavy use excludes cheap machines', () => {
	assert.ok( ! ids( Scoring.rank( config, items, answers( { freq: 'heavy', b: 10000 } ) ) ).includes( 4 ) );
} );

test( 'goal sets minimum speed', () => {
	assert.deepStrictEqual( ids( Scoring.rank( config, items, answers( { goal: 'interval', w: 90 } ) ) ).sort(), [ 2, 3 ] );
} );

test( 'cushion, space, incline and tech requirements', () => {
	assert.deepStrictEqual( ids( Scoring.rank( config, items, answers( { cushion: 'soft' } ) ) ).sort(), [ 2, 3 ] );
	assert.deepStrictEqual( ids( Scoring.rank( config, items, answers( { cushion: 'rehab' } ) ) ).sort(), [ 2, 3 ] );
	assert.deepStrictEqual( ids( Scoring.rank( config, items, answers( { space: 'foldable', b: 20000 } ) ) ), [ 1 ] );
	assert.deepStrictEqual( ids( Scoring.rank( config, items, answers( { incline: 'yes', b: 20000 } ) ) ), [ 2 ] );
	assert.deepStrictEqual( ids( Scoring.rank( config, items, answers( { tech: 'screen' } ) ) ), [ 3 ] );
	assert.deepStrictEqual( ids( Scoring.rank( config, items, answers( { tech: 'app' } ) ) ).sort(), [ 2, 3 ] );
} );

test( 'unknown numeric values do not fail hard rules', () => {
	const r = Scoring.rank( config, [ item( 9, 9000, { weight: null, speed: null } ) ], answers( { goal: 'run' } ) );
	assert.deepStrictEqual( ids( r ), [ 9 ] );
} );

test( 'best match is highest score; scores are deterministic', () => {
	const a = Scoring.rank( config, items, answers( { b: 35000 } ) );
	const b = Scoring.rank( config, items, answers( { b: 35000 } ) );
	assert.deepStrictEqual( a.items.map( ( p ) => [ p.id, p.matchPercentage ] ), b.items.map( ( p ) => [ p.id, p.matchPercentage ] ) );
	assert.strictEqual( a.items[ 0 ].isBestMatch, true );
	assert.ok( a.items.every( ( p ) => p.matchPercentage >= 70 && p.matchPercentage <= 99 ) );
	assert.ok( a.items[ 0 ].rawScore >= a.items[ 1 ].rawScore );
} );

test( 'cheaper than budget scores higher, bonus applies for rehab', () => {
	const cheap = Scoring.rank( config, [ item( 1, 7000, {} ) ], answers( { b: 20000 } ) ).items[ 0 ].rawScore;
	const dear = Scoring.rank( config, [ item( 1, 19000, {} ) ], answers( { b: 20000 } ) ).items[ 0 ].rawScore;
	assert.ok( cheap > dear );
	const base = Scoring.rank( config, [ item( 1, 12000, { weight: 130, rehab: true } ) ], answers( { cushion: 'standard' } ) ).items[ 0 ].rawScore;
	const withBonus = Scoring.rank( config, [ item( 1, 12000, { weight: 130, rehab: true } ) ], answers( { cushion: 'rehab' } ) ).items[ 0 ].rawScore;
	assert.strictEqual( Math.round( withBonus - base ), 5 );
} );

test( 'shop bias favors Treningspartner and penalises Maxa', () => {
	const score = ( shop ) => Scoring.rank( config, [ item( 1, 12000, {}, { shop } ) ], answers() ).items[ 0 ].rawScore;
	assert.ok( Math.abs( score( 'Treningspartner' ) - score( 'Annen' ) - 3.5 ) < 1e-9 );
	assert.ok( Math.abs( score( 'Annen' ) - score( 'Maxa' ) - 2 ) < 1e-9 );
} );

test( 'best offer is the biggest discount >= 5% that is not the best match', () => {
	const list = [ item( 1, 9000, {}, { old: 9100 } ), item( 2, 12000, { weight: 140 }, { old: 15000 } ), item( 3, 14000, {}, { old: 14100 } ) ];
	const r = Scoring.rank( config, list, answers( { b: 20000 } ) );
	const offer = r.items.filter( ( p ) => p.isBestOffer );
	assert.strictEqual( offer.length, 1 );
	assert.strictEqual( offer[ 0 ].id, 2 );
	assert.strictEqual( r.items[ 0 ].isBestMatch, true );
	assert.notStrictEqual( r.items[ 0 ].id, 2 );
} );

test( 'at most 4 results, no duplicates', () => {
	const many = [];
	for ( let i = 1; i <= 8; i++ ) {
		many.push( item( i, 12000 + i * 500, {}, { old: i % 2 ? 30000 : 0 } ) );
	}
	const r = Scoring.rank( config, many, answers() );
	assert.strictEqual( r.items.length, 4 );
	assert.strictEqual( new Set( ids( r ) ).size, 4 );
} );

test( 'a Treningspartner product is featured when available', () => {
	const many = [];
	for ( let i = 1; i <= 6; i++ ) {
		many.push( item( i, 6000 + i * 500, {} ) );
	}
	many.push( item( 99, 20000, { weight: 120 }, { shop: 'Treningspartner' } ) );
	const r = Scoring.rank( config, many.map( ( p ) => ( p.id === 99 ? p : Object.assign( p, { shop: 'Maxa' } ) ) ), answers( { b: 25000 } ) );
	assert.ok( r.items.some( ( p ) => p.shop === 'Treningspartner' ) );
	assert.strictEqual( r.items.length, 4 );
	assert.ok( r.items[ 0 ].isBestMatch );
} );

test( 'fallback relaxes to budget +20%, weight and speed >= 14, at a flat 70%', () => {
	const r = Scoring.rank( config, [ item( 1, 21000, { speed: 15, weight: 100 } ), item( 2, 30000, { speed: 15, weight: 100 } ) ], answers( { goal: 'interval', b: 20000 } ) );
	assert.strictEqual( r.isFallback, true );
	assert.deepStrictEqual( ids( r ), [ 1 ] );
	assert.strictEqual( r.items[ 0 ].matchPercentage, 70 );
} );

test( 'empty when nothing fits even the fallback', () => {
	const r = Scoring.rank( config, items, answers( { b: 5000, w: 180 } ) );
	assert.deepStrictEqual( r, { items: [], isFallback: true } );
} );

test( 'expert notes: fixed for best match/offer, conditional pool otherwise', () => {
	const list = [ item( 1, 9000, { weight: 130 } ), item( 2, 12000, { weight: 150, screen: true }, { old: 15000 } ), item( 3, 14000, { screen: true, weight: 100 } ) ];
	const r = Scoring.rank( config, list, answers( { b: 20000 } ) );
	const byId = Object.fromEntries( r.items.map( ( p ) => [ p.id, p ] ) );
	assert.strictEqual( byId[ r.items[ 0 ].id ].note, config.notes.best_match );
	assert.strictEqual( byId[ 2 ].note, config.notes.best_offer );
	assert.ok( r.items.every( ( p ) => p.note ) );
	const third = r.items.find( ( p ) => ! p.isBestMatch && ! p.isBestOffer );
	assert.ok( third.note.length > 0 );
} );

console.log( n + ' passed' );
