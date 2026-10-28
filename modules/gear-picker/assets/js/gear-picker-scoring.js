/**
 * Gear picker scoring: pure functions, no DOM. Works in the browser (window.GearEnginePickerScoring) and Node.
 *
 * Conditions are plain objects evaluated against a product and the quiz answers:
 *   { field, op: is|gte|lte|gt, value | ref (+ mul, add) }   product field vs. a value or an answer
 *   { step, op: is|gt, value }                               an answer
 *   { any: [...] } / { all: [...] }                          groups
 * Any condition may carry "if" (a condition); when that is false the condition is skipped (passes).
 * A numeric field that is unknown (null) neither fails a hard rule nor counts as a preference.
 */
( function ( root, factory ) {
	if ( typeof module === 'object' && module.exports ) {
		module.exports = factory();
	} else {
		root.GearEnginePickerScoring = factory();
	}
}( typeof self !== 'undefined' ? self : this, function () {
	'use strict';

	function compare( op, left, right ) {
		if ( op === 'is' ) {
			return left === right;
		}
		if ( left === null || left === undefined || right === null || right === undefined ) {
			return null;
		}
		switch ( op ) {
			case 'gte': return left >= right;
			case 'lte': return left <= right;
			case 'gt': return left > right;
			default: return null;
		}
	}

	function fieldValue( item, field ) {
		if ( field === 'price' || field === 'shop' || field === 'discount' ) {
			return item[ field ];
		}
		return item.specs && field in item.specs ? item.specs[ field ] : null;
	}

	function targetValue( cond, filters ) {
		if ( cond.ref === undefined ) {
			return cond.value;
		}
		var v = Number( filters[ cond.ref ] );
		if ( cond.mul !== undefined ) {
			v *= cond.mul;
		}
		if ( cond.add !== undefined ) {
			v += cond.add;
		}
		return v;
	}

	// true / false / null (unknown)
	function evaluate( cond, item, filters ) {
		if ( cond[ 'if' ] && evaluate( cond[ 'if' ], item, filters ) !== true ) {
			return true;
		}
		var results;
		if ( cond.any ) {
			results = cond.any.map( function ( c ) {
				return evaluate( c, item, filters );
			} );
			return results.indexOf( true ) !== -1 ? true : ( results.indexOf( null ) !== -1 ? null : false );
		}
		if ( cond.all ) {
			results = cond.all.map( function ( c ) {
				return evaluate( c, item, filters );
			} );
			return results.indexOf( false ) !== -1 ? false : ( results.indexOf( null ) !== -1 ? null : true );
		}
		if ( cond.step ) {
			return compare( cond.op, cond.op === 'gt' ? Number( filters[ cond.step ] ) : filters[ cond.step ], cond.value );
		}
		return compare( cond.op, fieldValue( item, cond.field ), targetValue( cond, filters ) );
	}

	function passes( conds, item, filters ) {
		return ( conds || [] ).every( function ( c ) {
			return evaluate( c, item, filters ) !== false;
		} );
	}

	function selectedOption( step, filters ) {
		return ( step.options || [] ).filter( function ( o ) {
			return o.val === filters[ step.id ];
		} )[ 0 ];
	}

	function defaults( config ) {
		var filters = {};
		config.steps.forEach( function ( step ) {
			if ( step.type === 'slider' ) {
				filters[ step.id ] = step.default;
			}
		} );
		return filters;
	}

	function hardRules( config, filters ) {
		var rules = [];
		config.steps.forEach( function ( step ) {
			if ( step.type === 'slider' ) {
				rules = rules.concat( step.rules || [] );
			} else {
				var opt = selectedOption( step, filters );
				rules = rules.concat( ( opt && opt.require ) || [] );
			}
		} );
		return rules;
	}

	function baseValue( from, filters ) {
		if ( from.ref ) {
			return Number( filters[ from.ref ] );
		}
		var map = from.map || {};
		var answer = filters[ from.step ];
		return answer in map ? map[ answer ] : from.default;
	}

	function score( config, item, filters ) {
		var s = config.scoring;
		var total = s.base;

		if ( s.price_margin ) {
			var budget = Number( filters[ s.price_margin.ref ] );
			var margin = ( budget - item.price ) / budget;
			if ( margin > 0 ) {
				total += margin * s.price_margin.factor;
			}
		}

		( s.margins || [] ).forEach( function ( m ) {
			var value = fieldValue( item, m.field );
			if ( value === null ) {
				return;
			}
			var diff = value - baseValue( m.from, filters );
			if ( diff > 0 ) {
				total += Math.min( m.cap, diff * m.per );
			}
		} );

		config.steps.forEach( function ( step ) {
			var opt = step.type === 'choice' ? selectedOption( step, filters ) : null;
			( ( opt && opt.bonus ) || [] ).forEach( function ( b ) {
				if ( evaluate( b, item, filters ) === true ) {
					total += b.points;
				}
			} );
		} );

		var shop = String( item.shop || '' ).toLowerCase();
		Object.keys( s.shop_bias || {} ).forEach( function ( name ) {
			if ( shop.indexOf( name ) !== -1 ) {
				total += s.shop_bias[ name ];
			}
		} );

		return total;
	}

	function expertNote( config, item, index, filters ) {
		var notes = config.notes || {};
		if ( item.isBestMatch && notes.best_match ) {
			return notes.best_match;
		}
		if ( item.isBestOffer && notes.best_offer ) {
			return notes.best_offer;
		}
		var pool = ( notes.pool || [] ).filter( function ( n ) {
			return evaluate( n.when, item, filters ) === true;
		} ).map( function ( n ) {
			return n.text;
		} ).concat( notes.generic || [] );
		return pool.length ? pool[ index % pool.length ] : '';
	}

	/**
	 * Rank products for the given answers.
	 *
	 * @param {Object} config  Picker config.
	 * @param {Array}  items   Products.
	 * @param {Object} filters Answers keyed by step id.
	 * @return {{items: Array, isFallback: boolean}}
	 */
	function rank( config, items, filters ) {
		var isFallback = false;
		var rules = hardRules( config, filters );
		var pool = items.filter( function ( item ) {
			return passes( rules, item, filters );
		} );

		if ( ! pool.length ) {
			isFallback = true;
			pool = items.filter( function ( item ) {
				return passes( config.fallback, item, filters );
			} );
		}

		var fallbackScore = config.scoring.fallback_score;
		var scored = pool.map( function ( item ) {
			var raw = isFallback ? fallbackScore : score( config, item, filters );
			return Object.assign( {}, item, {
				rawScore: raw,
				discount: item.old > item.price ? Math.round( ( ( item.old - item.price ) / item.old ) * 100 ) : 0,
				matchPercentage: Math.max( fallbackScore, Math.min( 99, Math.round( raw ) ) )
			} );
		} );
		scored.sort( function ( a, b ) {
			return b.rawScore - a.rawScore || a.price - b.price;
		} );

		var limit = config.limit || 4;
		var best = scored[ 0 ] || null;
		var offer = null;
		var minDiscount = config.scoring.min_offer_discount || 0;
		scored.forEach( function ( p ) {
			if ( p !== best && p.discount >= minDiscount && p.discount > 0 && ( ! offer || p.discount > offer.discount ) ) {
				offer = p;
			}
		} );

		var results = [];
		if ( best ) {
			best.isBestMatch = true;
			results.push( best );
		}
		if ( offer ) {
			offer.isBestOffer = true;
			results.push( offer );
		}
		scored.forEach( function ( p ) {
			if ( results.length < limit && results.indexOf( p ) === -1 ) {
				results.push( p );
			}
		} );

		var shopName = String( config.feature_shop || '' ).toLowerCase();
		if ( shopName ) {
			var isFeatured = function ( p ) {
				return String( p.shop || '' ).toLowerCase().indexOf( shopName ) !== -1;
			};
			var extra = scored.filter( function ( p ) {
				return isFeatured( p ) && results.indexOf( p ) === -1;
			} )[ 0 ];
			if ( extra && ! results.some( isFeatured ) ) {
				var last = results[ results.length - 1 ];
				if ( results.length >= limit && last && ! last.isBestMatch && ! last.isBestOffer ) {
					results.pop();
				}
				if ( results.length < limit ) {
					results.push( extra );
				}
			}
		}

		results.forEach( function ( p, i ) {
			p.note = expertNote( config, p, i, filters );
		} );

		return { items: results, isFallback: isFallback };
	}

	return { rank: rank, defaults: defaults, evaluate: evaluate, hardRules: hardRules };
} ) );
