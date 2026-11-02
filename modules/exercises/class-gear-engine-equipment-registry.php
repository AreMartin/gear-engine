<?php
/**
 * Structured equipment registry for rehabilitation and exercise protocols.
 *
 * Provides deterministic mapping of exercise equipment to everyday free alternatives
 * and relevant gear picker guides or product search queries.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles equipment normalization, free alternatives, and gear guide bridges.
 */
class Gear_Engine_Equipment_Registry {

	/**
	 * Returns the master equipment registry dictionary.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function get_registry(): array {
		static $registry = null;
		if ( null !== $registry ) {
			return $registry;
		}

		$hub_base = function_exists( 'home_url' ) ? home_url( '/kjopsveiledere/' ) : '/kjopsveiledere/';

		$registry = array(
			'strikk' => array(
				'key'            => 'strikk',
				'title'          => __( 'Treningsstrikk / Miniband', 'gear-engine' ),
				'free_alt'       => __( 'Oppklippet strømpebukse, elastisk skjerf eller sykkelmuffe', 'gear-engine' ),
				'picker_url'     => add_query_arg( array( 'type' => 'rehab-guide' ), $hub_base ),
				'picker_label'   => __( 'Se treningsstrikker i Rehab-guiden', 'gear-engine' ),
				'search_keyword' => 'treningsstrikk',
				'badge'          => __( 'Skånsom motstand', 'gear-engine' ),
				'is_bodyweight'  => false,
				'aliases'        => array( 'strikk', 'miniband', 'treningsstrikk', 'elastisk strikk', 'motstandsstrikk', 'rubber band', 'powerband', 'strikker' ),
			),
			'balansepute' => array(
				'key'            => 'balansepute',
				'title'          => __( 'Balansepute / Vippebrett', 'gear-engine' ),
				'free_alt'       => __( 'En myk sofapute eller et sammenbrettet ullpledd på gulvet', 'gear-engine' ),
				'picker_url'     => add_query_arg( array( 'type' => 'rehab-guide' ), $hub_base ),
				'picker_label'   => __( 'Finn balanseputer i Rehab-guiden', 'gear-engine' ),
				'search_keyword' => 'balansepute',
				'badge'          => __( 'Ledd- & stabilitetstrening', 'gear-engine' ),
				'is_bodyweight'  => false,
				'aliases'        => array( 'balansepute', 'vippebrett', 'balansebrett', 'balansetrener', 'balanse', 'balance pad' ),
			),
			'foam_roller' => array(
				'key'            => 'foam_roller',
				'title'          => __( 'Massasjerulle / Foam roller', 'gear-engine' ),
				'free_alt'       => __( 'En kjevle pakket inn i et badehåndkle eller en tennisball', 'gear-engine' ),
				'picker_url'     => add_query_arg( array( 'type' => 'rehab-guide' ), $hub_base ),
				'picker_label'   => __( 'Se massasjeruller i Rehab-guiden', 'gear-engine' ),
				'search_keyword' => 'foam roller',
				'badge'          => __( 'Muskelavspenning', 'gear-engine' ),
				'is_bodyweight'  => false,
				'aliases'        => array( 'foam-roller', 'foam roller', 'foamroller', 'massasjerulle', 'massasjeball', 'triggerpunktball', 'massasje' ),
			),
			'matte' => array(
				'key'            => 'matte',
				'title'          => __( 'Treningsmatte / Yogamatte', 'gear-engine' ),
				'free_alt'       => __( 'Et mykt teppe eller et dobbeltbrettet håndkle på gulvet', 'gear-engine' ),
				'picker_url'     => add_query_arg( array( 'kategori' => 'styrke' ), $hub_base ),
				'picker_label'   => __( 'Se treningsmatter i Styrkeguiden', 'gear-engine' ),
				'search_keyword' => 'treningsmatte',
				'badge'          => __( 'Underlagsdemping', 'gear-engine' ),
				'is_bodyweight'  => false,
				'aliases'        => array( 'matte', 'treningsmatte', 'yogamatte', 'treningsunderlag', 'gulvmatte' ),
			),
			'manualer' => array(
				'key'            => 'manualer',
				'title'          => __( 'Manualer / Hantler', 'gear-engine' ),
				'free_alt'       => __( 'Fylte vannflasker (0,5–1,5 liter), melkekartonger eller hermetikkbokser', 'gear-engine' ),
				'picker_url'     => add_query_arg( array( 'type' => 'manualer' ), $hub_base ),
				'picker_label'   => __( 'Se justerbare manualer i Styrkeguiden', 'gear-engine' ),
				'search_keyword' => 'manualer',
				'badge'          => __( 'Styrketrening', 'gear-engine' ),
				'is_bodyweight'  => false,
				'aliases'        => array( 'manualer', 'manual', 'hantler', 'vekter', 'handvekter', 'dumbbells' ),
			),
			'vektstang' => array(
				'key'            => 'vektstang',
				'title'          => __( 'Vektstang / Stang', 'gear-engine' ),
				'free_alt'       => __( 'Et kosteskaft for bevegelsestrening eller en ryggsekk fylt med bøker', 'gear-engine' ),
				'picker_url'     => add_query_arg( array( 'type' => 'kneboystativ' ), $hub_base ),
				'picker_label'   => __( 'Se vektstang og stativ i Styrkeguiden', 'gear-engine' ),
				'search_keyword' => 'vektstang',
				'badge'          => __( 'Styrketrening', 'gear-engine' ),
				'is_bodyweight'  => false,
				'aliases'        => array( 'vektstang', 'stang', 'vektstangsett', 'barbell' ),
			),
			'pedaltrener' => array(
				'key'            => 'pedaltrener',
				'title'          => __( 'Pedaltrener / Minisykkel', 'gear-engine' ),
				'free_alt'       => __( 'Sittende hælløft, tåløft og rolige sirkuleringsbevegelser med bena fra stol/sofa', 'gear-engine' ),
				'picker_url'     => add_query_arg( array( 'type' => 'rehab-guide' ), $hub_base ),
				'picker_label'   => __( 'Se pedaltrenere i Rehab-guiden', 'gear-engine' ),
				'search_keyword' => 'pedaltrener',
				'badge'          => __( 'Sirkulasjonstrening', 'gear-engine' ),
				'is_bodyweight'  => false,
				'aliases'        => array( 'pedaltrener', 'minisykkel', 'tråkkemaskin', 'sittesykkel for stol', 'pedaler' ),
			),
			'ergometersykkel' => array(
				'key'            => 'ergometersykkel',
				'title'          => __( 'Ergometersykkel / Sittesykkel', 'gear-engine' ),
				'free_alt'       => __( 'Rolig gange på flatt underlag med armpendling eller stasjonære høye kneløft', 'gear-engine' ),
				'picker_url'     => add_query_arg( array( 'type' => 'ergometersykkel' ), $hub_base ),
				'picker_label'   => __( 'Finn ergometersykkel med lavt innsteg', 'gear-engine' ),
				'search_keyword' => 'ergometersykkel',
				'badge'          => __( 'Leddvennlig kondisjon', 'gear-engine' ),
				'is_bodyweight'  => false,
				'aliases'        => array( 'ergometersykkel', 'sittesykkel', 'spinningsykkel', 'kondisjonssykkel', 'sykkel' ),
			),
			'egenvekt' => array(
				'key'            => 'egenvekt',
				'title'          => __( 'Egen kroppsvekt', 'gear-engine' ),
				'free_alt'       => __( 'Krever ingen redskaper – utføres med egen kropp', 'gear-engine' ),
				'picker_url'     => '',
				'picker_label'   => '',
				'search_keyword' => '',
				'badge'          => __( 'Egenvekt', 'gear-engine' ),
				'is_bodyweight'  => true,
				'aliases'        => array( 'egenvekt', 'kroppsvekt', 'ingen', 'kropp', 'uten utstyr' ),
			),
			'stol' => array(
				'key'            => 'stol',
				'title'          => __( 'Stol / Vegg / Trappetrinn', 'gear-engine' ),
				'free_alt'       => __( 'Bruk en vanlig, stabil spisestol, en ledig vegg eller et trappetrinn', 'gear-engine' ),
				'picker_url'     => '',
				'picker_label'   => '',
				'search_keyword' => '',
				'badge'          => __( 'Hverdagsinventar', 'gear-engine' ),
				'is_bodyweight'  => true,
				'aliases'        => array( 'stol', 'vegg', 'trapp', 'trappetrinn', 'bord', 'dørkarm', 'dør' ),
			),
		);

		/**
		 * Filters the master equipment registry dictionary.
		 *
		 * @param array<string, array<string, mixed>> $registry Master dictionary.
		 */
		return (array) apply_filters( 'gear_engine_equipment_registry', $registry );
	}

	/**
	 * Match an equipment term, alias, or text string to a registered record.
	 *
	 * @param string $term Equipment term or keyword.
	 * @return array<string, mixed>|null
	 */
	public static function resolve_term( string $term ): ?array {
		$clean = mb_strtolower( trim( $term ), 'UTF-8' );
		if ( '' === $clean ) {
			return null;
		}

		$registry = self::get_registry();

		// 1. Direct key match.
		if ( isset( $registry[ $clean ] ) ) {
			return $registry[ $clean ];
		}

		// 2. Exact alias match.
		foreach ( $registry as $item ) {
			if ( ! empty( $item['aliases'] ) && in_array( $clean, $item['aliases'], true ) ) {
				return $item;
			}
		}

		// 3. Substring / partial match on aliases.
		foreach ( $registry as $item ) {
			if ( empty( $item['aliases'] ) ) {
				continue;
			}
			foreach ( $item['aliases'] as $alias ) {
				if ( false !== mb_strpos( $clean, $alias ) || false !== mb_strpos( $alias, $clean ) ) {
					return $item;
				}
			}
		}

		return null;
	}

	/**
	 * Resolves a list of taxonomy terms or strings into structured equipment records.
	 *
	 * @param array<int, string> $terms List of equipment terms.
	 * @param bool               $exclude_bodyweight Whether to exclude pure bodyweight / furniture.
	 * @return array<string, array<string, mixed>> Keyed by equipment canonical key.
	 */
	public static function resolve_equipment_list( array $terms, bool $exclude_bodyweight = false ): array {
		$resolved = array();

		foreach ( $terms as $term ) {
			if ( ! is_scalar( $term ) ) {
				continue;
			}
			$info = self::resolve_term( (string) $term );
			if ( ! $info ) {
				continue;
			}
			if ( $exclude_bodyweight && ! empty( $info['is_bodyweight'] ) ) {
				continue;
			}
			if ( ! isset( $resolved[ $info['key'] ] ) ) {
				$resolved[ $info['key'] ] = $info;
			}
		}

		return $resolved;
	}
}
