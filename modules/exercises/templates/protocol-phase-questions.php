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
		<h2 class="gear-engine-program__title"><?php echo esc_html( $question_title ); ?></h2>
		<p class="gear-engine-program__summary"><?php esc_html_e( 'Svar på fire korte spørsmål, så velger vi øvelser som passer der du er nå.', 'gear-engine' ); ?></p>
	</header>
	<form method="get" class="gear-engine-program__questions">
		<input type="hidden" name="region" value="<?php echo esc_attr( $region ); ?>">
		<input type="hidden" name="dx" value="<?php echo esc_attr( $dx ); ?>">
		<?php foreach ( $gear_engine_questions as $gear_engine_key => $gear_engine_question ) : ?>
			<fieldset class="gear-engine-program__question">
				<legend><?php echo esc_html( $gear_engine_question[0] ); ?></legend>
				<?php foreach ( $gear_engine_question[1] as $gear_engine_value => $gear_engine_label ) : ?>
					<label><input type="radio" name="<?php echo esc_attr( $gear_engine_key ); ?>" value="<?php echo esc_attr( $gear_engine_value ); ?>" required> <?php echo esc_html( $gear_engine_label ); ?></label>
				<?php endforeach; ?>
			</fieldset>
		<?php endforeach; ?>
		<button type="submit" class="gear-engine-program__btn"><?php esc_html_e( 'Vis øvelser', 'gear-engine' ); ?></button>
	</form>
</div>
