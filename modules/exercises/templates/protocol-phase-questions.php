<?php
/**
 * Phase questions shown before an automatic program.
 *
 * Expects $region, $dx and $question_title from Gear_Engine_Protocol_Viewer::render().
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

$gear_engine_questions = array(
	'warn'  => array(
		__( 'Har du nummenhet, kraftsvikt eller smerter som øker raskt?', 'gear-engine' ),
		array(
			'no'  => __( 'Nei', 'gear-engine' ),
			'yes' => __( 'Ja', 'gear-engine' ),
		),
	),
	'pain'  => array(
		__( 'Hvor vondt har du det akkurat nå?', 'gear-engine' ),
		array(
			'severe'   => __( 'Mye smerte, også i hvile', 'gear-engine' ),
			'moderate' => __( 'Moderat, kommer ved belastning', 'gear-engine' ),
			'mild'     => __( 'Lite eller ingen smerte', 'gear-engine' ),
		),
	),
	'func'  => array(
		__( 'Hvordan fungerer hverdagen?', 'gear-engine' ),
		array(
			'limited' => __( 'Jeg klarer ikke vanlige gjøremål', 'gear-engine' ),
			'partial' => __( 'Jeg klarer det meste, men med begrensninger', 'gear-engine' ),
			'normal'  => __( 'Jeg klarer det jeg vil', 'gear-engine' ),
		),
	),
	'since' => array(
		__( 'Hvor lenge har du hatt plagene?', 'gear-engine' ),
		array(
			'acute'    => __( 'Under 2 uker', 'gear-engine' ),
			'subacute' => __( '2–6 uker', 'gear-engine' ),
			'chronic'  => __( 'Mer enn 6 uker', 'gear-engine' ),
		),
	),
);
?>
<div class="gear-engine-program">
	<header class="gear-engine-program__head">
		<div class="gear-engine-program__badge">
			<span class="gear-engine-program__badge-dot"></span>
			<?php esc_html_e( 'Fasetilpasning · 30 sekunder', 'gear-engine' ); ?>
		</div>
		<h2 class="gear-engine-program__title"><?php echo esc_html( $question_title ); ?></h2>
		<p class="gear-engine-program__summary"><?php esc_html_e( 'Svar på fire korte spørsmål, så velger vi øvelser som passer der du er i rehabiliteringsløpet akkurat nå.', 'gear-engine' ); ?></p>
	</header>

	<form method="get" class="gear-engine-program__questions">
		<?php
		// Keep other query arguments (such as page_id on plain permalinks) when the form is submitted.
		$gear_engine_own = array( 'region', 'dx', 'warn', 'pain', 'func', 'since' );
		foreach ( $_GET as $gear_engine_name => $gear_engine_val ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( in_array( $gear_engine_name, $gear_engine_own, true ) || ! is_scalar( $gear_engine_val ) ) {
				continue;
			}
			?>
			<input type="hidden" name="<?php echo esc_attr( sanitize_key( $gear_engine_name ) ); ?>" value="<?php echo esc_attr( sanitize_text_field( wp_unslash( (string) $gear_engine_val ) ) ); ?>">
			<?php
		}
		?>
		<input type="hidden" name="region" value="<?php echo esc_attr( $region ); ?>">
		<input type="hidden" name="dx" value="<?php echo esc_attr( $dx ); ?>">
		<?php foreach ( $gear_engine_questions as $gear_engine_key => $gear_engine_question ) : ?>
			<fieldset class="gear-engine-program__question">
				<legend class="gear-engine-program__question-legend"><?php echo esc_html( $gear_engine_question[0] ); ?></legend>
				<div class="gear-engine-program__options">
					<?php foreach ( $gear_engine_question[1] as $gear_engine_value => $gear_engine_label ) : ?>
						<label class="gear-engine-program__option">
							<input type="radio" name="<?php echo esc_attr( $gear_engine_key ); ?>" value="<?php echo esc_attr( $gear_engine_value ); ?>" required class="gear-engine-program__radio">
							<span class="gear-engine-program__option-label"><?php echo esc_html( $gear_engine_label ); ?></span>
						</label>
					<?php endforeach; ?>
				</div>
			</fieldset>
		<?php endforeach; ?>
		<div class="gear-engine-program__submit-wrap">
			<button type="submit" class="gear-engine-program__btn">
				<span><?php esc_html_e( 'Vis tilpasset treningsprogram', 'gear-engine' ); ?></span>
				<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="var(--ge-icon-stroke, 1.75px)" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14m-6-6l6 6-6 6"/></svg>
			</button>
		</div>
	</form>
</div>
