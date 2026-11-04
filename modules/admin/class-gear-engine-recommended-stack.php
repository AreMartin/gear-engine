<?php
/**
 * One-click installer and status checker for the recommended theme & plugin stack.
 *
 * Checks and installs GeneratePress (Theme) and GenerateBlocks (Plugin)
 * from WordPress.org with zero frontend bloat.
 *
 * @package GearEngine
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

if ( ! class_exists( 'Gear_Engine_Quiet_Skin' ) && class_exists( 'WP_Upgrader_Skin' ) ) {
	/**
	 * Quiet upgrader skin suppressing raw browser output during automated installations.
	 */
	class Gear_Engine_Quiet_Skin extends WP_Upgrader_Skin {
		/**
		 * Suppress feedback string output.
		 *
		 * @param string $string Feedback string.
		 * @param mixed  ...$args Optional arguments.
		 * @return void
		 */
		public function feedback( $string, ...$args ) {}

		/**
		 * Suppress HTML header output.
		 *
		 * @return void
		 */
		public function header() {}

		/**
		 * Suppress HTML footer output.
		 *
		 * @return void
		 */
		public function footer() {}
	}
}

/**
 * Recommended Stack Manager for GeneratePress and GenerateBlocks.
 */
class Gear_Engine_Recommended_Stack {

	public const THEME_SLUG   = 'generatepress';
	public const PLUGIN_SLUG  = 'generateblocks';
	public const PLUGIN_FILE  = 'generateblocks/plugin.php';

	/**
	 * Get the live installation and activation status of the recommended stack.
	 *
	 * Performs only local filesystem and option checks (zero remote HTTP requests).
	 *
	 * @return array{
	 *     theme: array{slug:string,name:string,installed:bool,active:bool,version:string},
	 *     plugin: array{slug:string,name:string,installed:bool,active:bool,version:string},
	 *     complete: bool
	 * }
	 */
	public static function get_status(): array {
		// 1. Check Theme: GeneratePress.
		$theme_installed = false;
		$theme_active    = false;
		$theme_version   = '';

		if ( function_exists( 'wp_get_theme' ) ) {
			$theme = wp_get_theme( self::THEME_SLUG );
			if ( $theme && $theme->exists() ) {
				$theme_installed = true;
				$theme_version   = (string) $theme->get( 'Version' );
			}
		}

		if ( function_exists( 'get_template' ) ) {
			$current_template   = get_template();
			$current_stylesheet = function_exists( 'get_stylesheet' ) ? get_stylesheet() : $current_template;
			if ( self::THEME_SLUG === $current_template || self::THEME_SLUG === $current_stylesheet ) {
				$theme_active = true;
			}
		}

		// 2. Check Plugin: GenerateBlocks.
		$plugin_installed = false;
		$plugin_active    = false;
		$plugin_version   = '';

		if ( defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE ) ) {
			$plugin_installed = true;
			if ( function_exists( 'get_plugin_data' ) ) {
				$data = get_plugin_data( WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE, false, false );
				if ( ! empty( $data['Version'] ) ) {
					$plugin_version = (string) $data['Version'];
				}
			}
		}

		if ( function_exists( 'is_plugin_active' ) ) {
			$plugin_active = is_plugin_active( self::PLUGIN_FILE );
		} elseif ( function_exists( 'get_option' ) ) {
			$active_plugins = (array) get_option( 'active_plugins', array() );
			$plugin_active  = in_array( self::PLUGIN_FILE, $active_plugins, true );
		}

		$complete = $theme_active && $plugin_active;

		return array(
			'theme'    => array(
				'slug'      => self::THEME_SLUG,
				'name'      => 'GeneratePress',
				'installed' => $theme_installed,
				'active'    => $theme_active,
				'version'   => $theme_version,
			),
			'plugin'   => array(
				'slug'      => self::PLUGIN_SLUG,
				'file'      => self::PLUGIN_FILE,
				'name'      => 'GenerateBlocks',
				'installed' => $plugin_installed,
				'active'    => $plugin_active,
				'version'   => $plugin_version,
			),
			'complete' => $complete,
		);
	}

	/**
	 * Automatically install (if missing) and activate GeneratePress and GenerateBlocks.
	 *
	 * Only called upon explicit admin request protected by nonces and manage_options capability.
	 *
	 * @return array{success:bool,messages:string[],errors:string[]}
	 */
	public static function install_and_activate(): array {
		if ( ! current_user_can( 'manage_options' ) ) {
			return array(
				'success'  => false,
				'messages' => array(),
				'errors'   => array( __( 'Du har ikke rettigheter til å installere temaer eller utvidelser.', 'gear-engine' ) ),
			);
		}

		$status   = self::get_status();
		$messages = array();
		$errors   = array();

		// Load WordPress admin upgrade dependencies.
		if ( defined( 'ABSPATH' ) ) {
			if ( ! function_exists( 'themes_api' ) && file_exists( ABSPATH . 'wp-admin/includes/theme-install.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/theme-install.php';
			}
			if ( ! function_exists( 'plugins_api' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin-install.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			}
			if ( ! class_exists( 'WP_Upgrader' ) && file_exists( ABSPATH . 'wp-admin/includes/class-wp-upgrader.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';
			}
			if ( ! function_exists( 'switch_theme' ) && file_exists( ABSPATH . 'wp-admin/includes/theme.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/theme.php';
			}
			if ( ! function_exists( 'activate_plugin' ) && file_exists( ABSPATH . 'wp-admin/includes/plugin.php' ) ) {
				require_once ABSPATH . 'wp-admin/includes/plugin.php';
			}
		}

		// 1. Install & Activate Theme (GeneratePress).
		if ( ! $status['theme']['installed'] ) {
			if ( function_exists( 'themes_api' ) && class_exists( 'Theme_Upgrader' ) ) {
				$api = themes_api(
					'theme_information',
					array(
						'slug'   => self::THEME_SLUG,
						'fields' => array( 'sections' => false ),
					)
				);

				if ( is_wp_error( $api ) ) {
					$errors[] = sprintf(
						/* translators: %s: Error message */
						__( 'Kunne ikke hente GeneratePress fra wordpress.org: %s', 'gear-engine' ),
						$api->get_error_message()
					);
				} elseif ( ! empty( $api->download_link ) ) {
					$skin     = class_exists( 'Gear_Engine_Quiet_Skin' ) ? new Gear_Engine_Quiet_Skin() : null;
					$upgrader = new Theme_Upgrader( $skin );
					$result   = $upgrader->install( $api->download_link );

					if ( is_wp_error( $result ) ) {
						$errors[] = sprintf(
							/* translators: %s: Error message */
							__( 'Installasjon av GeneratePress feilet: %s', 'gear-engine' ),
							$result->get_error_message()
						);
					} else {
						$messages[] = __( 'GeneratePress tema ble installert.', 'gear-engine' );
					}
				}
			}
		}

		// Activate GeneratePress if installed.
		if ( function_exists( 'switch_theme' ) ) {
			$theme = function_exists( 'wp_get_theme' ) ? wp_get_theme( self::THEME_SLUG ) : null;
			if ( $theme && $theme->exists() ) {
				switch_theme( self::THEME_SLUG );
				$messages[] = __( 'GeneratePress ble aktivert som aktivt tema.', 'gear-engine' );
			}
		}

		// 2. Install & Activate Plugin (GenerateBlocks).
		if ( ! $status['plugin']['installed'] ) {
			if ( function_exists( 'plugins_api' ) && class_exists( 'Plugin_Upgrader' ) ) {
				$api = plugins_api(
					'plugin_information',
					array(
						'slug'   => self::PLUGIN_SLUG,
						'fields' => array( 'sections' => false ),
					)
				);

				if ( is_wp_error( $api ) ) {
					$errors[] = sprintf(
						/* translators: %s: Error message */
						__( 'Kunne ikke hente GenerateBlocks fra wordpress.org: %s', 'gear-engine' ),
						$api->get_error_message()
					);
				} elseif ( ! empty( $api->download_link ) ) {
					$skin     = class_exists( 'Gear_Engine_Quiet_Skin' ) ? new Gear_Engine_Quiet_Skin() : null;
					$upgrader = new Plugin_Upgrader( $skin );
					$result   = $upgrader->install( $api->download_link );

					if ( is_wp_error( $result ) ) {
						$errors[] = sprintf(
							/* translators: %s: Error message */
							__( 'Installasjon av GenerateBlocks feilet: %s', 'gear-engine' ),
							$result->get_error_message()
						);
					} else {
						$messages[] = __( 'GenerateBlocks utvidelsen ble installert.', 'gear-engine' );
					}
				}
			}
		}

		// Activate GenerateBlocks if installed.
		if ( function_exists( 'activate_plugin' ) ) {
			if ( defined( 'WP_PLUGIN_DIR' ) && file_exists( WP_PLUGIN_DIR . '/' . self::PLUGIN_FILE ) ) {
				$act_result = activate_plugin( self::PLUGIN_FILE );
				if ( is_wp_error( $act_result ) ) {
					$errors[] = sprintf(
						/* translators: %s: Error message */
						__( 'Aktivering av GenerateBlocks feilet: %s', 'gear-engine' ),
						$act_result->get_error_message()
					);
				} else {
					$messages[] = __( 'GenerateBlocks ble aktivert.', 'gear-engine' );
				}
			}
		}

		$success = empty( $errors );

		return array(
			'success'  => $success,
			'messages' => $messages,
			'errors'   => $errors,
		);
	}
}
