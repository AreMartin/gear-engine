/**
 * Gear Engine protocol page: interactive stepper, lazy video embeds, radio selection sync, and print.
 *
 * @package GearEngine
 */
( function () {
	'use strict';

	var ALLOWED = /^https:\/\/(exorlive\.com\/video\/|www\.youtube-nocookie\.com\/embed\/|player\.vimeo\.com\/video\/)/;

	function playVideo( box ) {
		var src = box.getAttribute( 'data-embed' ) || '';
		if ( ! ALLOWED.test( src ) ) {
			return;
		}
		var frame = document.createElement( 'iframe' );
		frame.src = src;
		frame.title = box.getAttribute( 'data-title' ) || '';
		frame.allow = 'autoplay; fullscreen; picture-in-picture';
		frame.setAttribute( 'allowfullscreen', '' );
		frame.setAttribute( 'referrerpolicy', 'strict-origin-when-cross-origin' );
		box.textContent = '';
		box.appendChild( frame );
	}

	function syncRadioSelection( radio ) {
		if ( ! radio || ! radio.name ) {
			return;
		}
		var form = radio.closest( '.gear-engine-program__questions' );
		if ( ! form ) {
			return;
		}
		var radios = form.querySelectorAll( 'input[type="radio"][name="' + CSS.escape( radio.name ) + '"]' );
		radios.forEach( function ( r ) {
			var opt = r.closest( '.gear-engine-program__option' );
			if ( opt ) {
				opt.classList.toggle( 'gear-engine-program__option--selected', r.checked );
			}
		} );
	}

	function initStepper( root ) {
		var activePhase = 0;

		function setPhase( phaseIndex ) {
			activePhase = phaseIndex;

			// Update phase tab buttons.
			var phaseTabs = root.querySelectorAll( '.gear-engine-program__phase-tab' );
			phaseTabs.forEach( function ( tab ) {
				var idx = parseInt( tab.getAttribute( 'data-phase-index' ), 10 );
				var isActive = ( idx === phaseIndex );
				tab.classList.toggle( 'gear-engine-program__phase-tab--active', isActive );
				tab.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );

			// Update stepper phases.
			var stepperPhases = root.querySelectorAll( '.gear-engine-program__stepper-phase' );
			stepperPhases.forEach( function ( sp ) {
				var idx = parseInt( sp.getAttribute( 'data-phase' ), 10 );
				var isActive = ( idx === phaseIndex );
				sp.style.display = isActive ? '' : 'none';
				sp.classList.toggle( 'gear-engine-program__stepper-phase--active', isActive );
			} );

			setStep( phaseIndex, 0 );
		}

		function setStep( phaseIndex, stepIndex ) {
			var phaseEl = root.querySelector( '.gear-engine-program__stepper-phase[data-phase="' + phaseIndex + '"]' );
			if ( ! phaseEl ) {
				return;
			}

			var tabs = phaseEl.querySelectorAll( '.gear-engine-program__stepper-tab' );
			var cards = phaseEl.querySelectorAll( '.gear-engine-program__step-card' );
			var completionCard = phaseEl.querySelector( '.gear-engine-program__completion-card' );
			var nav = phaseEl.querySelector( '.gear-engine-program__stepper-nav' );
			var navPrev = phaseEl.querySelector( '[data-nav="prev"]' );
			var navNext = phaseEl.querySelector( '[data-nav="next"]' );
			var navFinish = phaseEl.querySelector( '[data-nav="finish"]' );
			var totalSteps = cards.length;

			if ( completionCard ) {
				completionCard.style.display = 'none';
			}
			if ( nav ) {
				nav.style.display = '';
			}

			if ( stepIndex < 0 ) {
				stepIndex = 0;
			}
			if ( stepIndex >= totalSteps ) {
				stepIndex = totalSteps - 1;
			}

			phaseEl.setAttribute( 'data-active-step', stepIndex );

			// Update exercise tabs.
			tabs.forEach( function ( tab, idx ) {
				var isActive = ( idx === stepIndex );
				tab.classList.toggle( 'gear-engine-program__stepper-tab--active', isActive );
				tab.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
				if ( isActive && typeof tab.scrollIntoView === 'function' ) {
					tab.scrollIntoView( { behavior: 'smooth', block: 'nearest', inline: 'center' } );
				}
			} );

			// Update exercise cards.
			cards.forEach( function ( card, idx ) {
				var isActive = ( idx === stepIndex );
				card.style.display = isActive ? '' : 'none';
				card.classList.toggle( 'gear-engine-program__step-card--active', isActive );
			} );

			// Update navigation buttons.
			if ( navPrev ) {
				navPrev.disabled = ( 0 === stepIndex );
			}
			if ( navNext && navFinish ) {
				if ( stepIndex === totalSteps - 1 ) {
					navNext.style.display = 'none';
					navFinish.style.display = 'inline-flex';
				} else {
					navNext.style.display = 'inline-flex';
					navFinish.style.display = 'none';
				}
			}
		}

		function showCompletion( phaseIndex ) {
			var phaseEl = root.querySelector( '.gear-engine-program__stepper-phase[data-phase="' + phaseIndex + '"]' );
			if ( ! phaseEl ) {
				return;
			}
			var cards = phaseEl.querySelectorAll( '.gear-engine-program__step-card' );
			cards.forEach( function ( card ) {
				card.style.display = 'none';
			} );
			var nav = phaseEl.querySelector( '.gear-engine-program__stepper-nav' );
			if ( nav ) {
				nav.style.display = 'none';
			}
			var completionCard = phaseEl.querySelector( '.gear-engine-program__completion-card' );
			if ( completionCard ) {
				completionCard.style.display = 'block';
			}
		}

		function setViewMode( mode ) {
			root.setAttribute( 'data-active-mode', mode );
			var modeBtns = root.querySelectorAll( '.gear-engine-program__mode-btn' );
			modeBtns.forEach( function ( btn ) {
				var isActive = ( btn.getAttribute( 'data-view-mode' ) === mode );
				btn.classList.toggle( 'gear-engine-program__mode-btn--active', isActive );
				btn.setAttribute( 'aria-selected', isActive ? 'true' : 'false' );
			} );
			var stepperView = root.querySelector( '.gear-engine-program__stepper-view' );
			var listView = root.querySelector( '.gear-engine-program__list-view' );
			if ( stepperView && listView ) {
				if ( 'stepper' === mode ) {
					stepperView.style.display = '';
					listView.style.display = 'none';
				} else {
					stepperView.style.display = 'none';
					listView.style.display = '';
				}
			}
		}

		// Click handling on the program container.
		root.addEventListener( 'click', function ( event ) {
			var target = event.target;
			if ( ! ( target instanceof Element ) ) {
				return;
			}

			// View mode toggle.
			var modeBtn = target.closest( '.gear-engine-program__mode-btn' );
			if ( modeBtn ) {
				var mode = modeBtn.getAttribute( 'data-view-mode' ) || 'stepper';
				setViewMode( mode );
				return;
			}

			// Phase tab toggle.
			var phaseTab = target.closest( '.gear-engine-program__phase-tab' );
			if ( phaseTab ) {
				var pIdx = parseInt( phaseTab.getAttribute( 'data-phase-index' ), 10 );
				if ( ! isNaN( pIdx ) ) {
					setPhase( pIdx );
				}
				return;
			}

			// Exercise tab toggle.
			var stepTab = target.closest( '.gear-engine-program__stepper-tab' );
			if ( stepTab ) {
				var sIdx = parseInt( stepTab.getAttribute( 'data-step' ), 10 );
				if ( ! isNaN( sIdx ) ) {
					setStep( activePhase, sIdx );
				}
				return;
			}

			// Stepper navigation buttons.
			var navBtn = target.closest( '.gear-engine-program__nav-btn' );
			if ( navBtn ) {
				var action = navBtn.getAttribute( 'data-nav' );
				var currentPhaseEl = root.querySelector( '.gear-engine-program__stepper-phase[data-phase="' + activePhase + '"]' );
				var currentStep = currentPhaseEl ? parseInt( currentPhaseEl.getAttribute( 'data-active-step' ) || '0', 10 ) : 0;
				if ( 'next' === action ) {
					setStep( activePhase, currentStep + 1 );
				} else if ( 'prev' === action ) {
					setStep( activePhase, currentStep - 1 );
				} else if ( 'finish' === action ) {
					showCompletion( activePhase );
				}
				return;
			}

			// Restart workout.
			if ( target.closest( '[data-action="restart-workout"]' ) ) {
				setStep( activePhase, 0 );
				return;
			}

			// Go to next phase.
			var nextPhaseBtn = target.closest( '[data-action="next-phase"]' );
			if ( nextPhaseBtn ) {
				var targetPhase = parseInt( nextPhaseBtn.getAttribute( 'data-target-phase' ), 10 );
				if ( ! isNaN( targetPhase ) ) {
					setPhase( targetPhase );
				}
				return;
			}

			// Jump from list view into stepper.
			var jumpBtn = target.closest( '.gear-engine-program__start-step-btn' );
			if ( jumpBtn ) {
				var jp = parseInt( jumpBtn.getAttribute( 'data-jump-phase' ), 10 );
				var js = parseInt( jumpBtn.getAttribute( 'data-jump-step' ), 10 );
				setViewMode( 'stepper' );
				if ( ! isNaN( jp ) ) {
					setPhase( jp );
					if ( ! isNaN( js ) ) {
						setStep( jp, js );
					}
				}
				return;
			}
		} );

		// Initial activation.
		setPhase( 0 );
	}

	// Global event listeners.
	document.addEventListener( 'click', function ( event ) {
		var target = event.target;
		if ( ! ( target instanceof Element ) ) {
			return;
		}
		var play = target.closest( '.gear-engine-program__play' );
		if ( play ) {
			var box = play.closest( '.gear-engine-program__video' );
			if ( box ) {
				playVideo( box );
			}
			return;
		}
		if ( target.closest( '.gear-engine-program [data-action="print"]' ) ) {
			window.print();
		}
	} );

	document.addEventListener( 'change', function ( event ) {
		var target = event.target;
		if ( target instanceof HTMLInputElement && target.type === 'radio' && target.closest( '.gear-engine-program' ) ) {
			syncRadioSelection( target );
		}
	} );

	document.addEventListener( 'DOMContentLoaded', function () {
		var programs = document.querySelectorAll( '.gear-engine-program' );
		programs.forEach( function ( p ) {
			initStepper( p );
		} );

		var checked = document.querySelectorAll( '.gear-engine-program input[type="radio"]:checked' );
		checked.forEach( function ( r ) {
			syncRadioSelection( r );
		} );
	} );
}() );
