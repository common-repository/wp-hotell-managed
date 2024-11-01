<?php
/**
 * Plugin Name: 			WP Managed
 * Description: 			We help you as an admin to efficiently manage and debug WordPress websites and streamline your backend. Debug, Warn, Show, Hide, Enable, Disable, Limit and more, in one plugin
 * Version: 				1.0.4
 * Author: 					United Works
 * Author URI: 				https://unitedworks.no/
 * Requires: 				5.9 or higher
 * License: 				GPLv3 or later
 * License URI:       		http://www.gnu.org/licenses/gpl-3.0.html
 * Requires PHP: 			5.6
 * Tested up to:            6.5.5
 * Text Domain:				wphm	
 * Domain Path:				/languages
 *
 * Copyright 2022 			Unted Works 		hei@unitedworks.no
 *
 * Original Plugin URI: 	https://unitedworks.no
 * Original Author URI: 	https://unitedworks.no
 *
 * NOTAT:
 *
 * Dette programmet er gratis programvare; Du kan distribuere den og / eller endre
 * den under betingelsene i GNU General Public License, versjon 3, som
 * utgitt av Free Software Foundation.
 *
 * Dette programmet er distribuert i håp om at det vil være nyttig,
 * men UTEN NOEN GARANTI; uten engang den underforståtte garantien fra
 * SALGSMIDLER eller egnethet til en bestemt hensikt. Se
 * GNU General Public License for mer detaljer.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}


class WPHotellManaged
{

    private $options;

    public function __construct()
    {
		$this->options = (array) get_option( 'wphm_options' );
		
		add_action( 'after_setup_theme', array( $this, 'wphm_hide_update_notifications' ) );
		add_action( 'admin_menu', array( $this, 'wphm_options_page' ) );
		add_action( 'plugins_loaded', array( $this, 'wphm_load_translations' ) );
		add_filter( 'authenticate',  array( $this, 'wphm_auth_signon' ), 10001, 3 );
		add_action( 'wp_login_failed',  array( $this, 'wphm_logfeil' ), 10, 1 );
		add_action( 'wp_login',  array( $this, 'wphm_remove_user_ip' ), 10, 2 );
		add_action( 'after_password_reset',  array( $this, 'wphm_remove_user_ip' ), 10, 1 ); 
		add_action( 'login_enqueue_scripts', array( $this, 'wphm_frontlogoen' ) );
		add_action( 'login_init', array( $this, 'wphm_login_init_action' ) );
		add_action( 'admin_print_scripts', array( $this, 'wphm_disable_admin_notices' ) );
		add_action( 'admin_init', array( $this, 'wphm_disable_auto_updates' ) );
		add_action( 'admin_print_scripts', array( $this, 'wphm_set_admin_notices' ) );
		add_action( 'admin_init', array( $this, 'wphm_check_wp_debug_define' ) );
		if ( is_admin() ) {
			register_activation_hook( __FILE__, array( $this, 'wphm_activation' ) );
			register_deactivation_hook( __FILE__, array( $this, 'wphm_deactivation' ) );
		}
    }


	public function wphm_options_page() {
		if ( !current_user_can( 'manage_options' ) ) {
			return;
		}
		$wphm_page = add_options_page( __('WP Managed', 'wphm' ), __('WP Managed', 'wphm' ), 'manage_options', 'wphotell-managed', array( $this, 'wphm_options_page_content' ) );
		add_action( $wphm_page, array( $this, 'wphm_admin_styles' ) );
	}


	public function wphm_admin_styles() {
		wp_enqueue_style( 'wphm_style', plugins_url( 'assets/css/style.css', __FILE__ ) );
	}


	public function wphm_load_translations() {
		load_plugin_textdomain( 'wphm', FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}


	public function wphm_options_page_content() {
		
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		if ( !empty( $_POST ) ) {
			check_admin_referer( 'wphm-options-nonce' );
			
			$wphm_options = $this->wphm_sanitize( (array) $_POST['wphm-options'] );
			
			update_option( 'wphm_options', $wphm_options );
			wp_redirect( $_SERVER['HTTP_REFERER'] ); 
		}
		
		$wphm_options = (array) get_option( 'wphm_options' );
		$wphm_options = wp_parse_args( $wphm_options, $this->wphm_get_defaults() );
		
	?>
	<div class="wrap wphm-wrapper">
		<h1><?php _e( 'WP Managed', 'wphm' ); ?></h1>
		<span><img src="<?php echo plugins_url( 'assets/img/loginmerke.svg', __FILE__ ); ?>"></span>
		<br />
		<form action="<?php menu_page_url( 'wphotell-managed' ); ?>" method="post">
			<?php wp_nonce_field( 'wphm-options-nonce' ); ?>
			<table class="form-table">
				<tr>
					<td>
						<input type="checkbox" id="wphm_limit_login_attempts" name="wphm-options[limit_login_attempts]" <?php checked( $wphm_options['limit_login_attempts'] ); ?> value="1"/>
						<label for="wphm_limit_login_attempts"><?php _e( 'Limit number of login attempts (3 times)', 'wphm' ); ?></label>
						<br />
						<input type="checkbox" id="wphm_show_logo_managed" name="wphm-options[show_logo_managed]" <?php checked( $wphm_options['show_logo_managed'] ); ?> value="1"/>
						<label for="wphm_show_logo_managed"><?php _e( 'Show "This site is Managed" login logo', 'wphm' ); ?></label>
						<br />
						<input type="checkbox" id="wphm_disable_admin_notices" name="wphm-options[disable_admin_notices]" <?php checked( $wphm_options['disable_admin_notices'] ); ?> value="1"/>
						<label for="wphm_disable_admin_notices"><?php _e( 'Disable admin notices', 'wphm' ); ?></label>
						<br />
						<input type="checkbox" id="wphm_disable_automatic_updates" name="wphm-options[disable_automatic_updates]" <?php checked( $wphm_options['disable_automatic_updates']); ?> value="1"/>
						<label for="wphm_disable_automatic_updates"><?php _e( 'Disable automatic updates', 'wphm' ); ?></label>
						<br />
						<input type="checkbox" id="wphm_hide_core_updates" name="wphm-options[hide_core_updates]" <?php checked( $wphm_options['hide_core_updates'] ); ?> value="1"/>
						<label for="wphm_hide_core_updates"><?php _e( 'Hide WordPress Core updates messages', 'wphm' ); ?></label>
						<br />
						<input type="checkbox" id="wphm_hide_themes_updates" name="wphm-options[hide_themes_updates]" <?php checked( $wphm_options['hide_themes_updates'] ); ?> value="1"/>
						<label for="wphm_hide_themes_updates"><?php _e( 'Hide Themes updates messages', 'wphm' ); ?></label>
						<br />
						<input type="checkbox" id="wphm_hide_plugins_updates" name="wphm-options[hide_plugins_updates]" <?php checked( $wphm_options['hide_plugins_updates'] ); ?> value="1"/>
						<label for="wphm_hide_plugins_updates"><?php _e( 'Hide Plugins updates messages', 'wphm' ); ?></label>
						<br />
						<br />
						<br />
						<input type="checkbox" id="wphm_show_staging_warning" name="wphm-options[show_staging_warning]" <?php checked( $wphm_options['show_staging_warning']); ?> value="1"/>
						<label for="wphm_show_staging_warning"><?php _e( 'Show Staging warning if "staging" is detected in url', 'wphm' ); ?></label>
						<br />
						<input type="checkbox" id="wphm_enable_debugging_mode" name="wphm-options[enable_debugging_mode]" <?php checked( $wphm_options['enable_debugging_mode']); ?> value="1"/>
						<label for="wphm_enable_debugging_mode">
							<?php printf (
								__( 'Enable %s', 'wphm' ),
								'<a href="https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/#wp_debug" rel="noopener noreferrer" target="_blank">WP_DEBUG</a>'
							); ?>
						</label>
						<br />
						<input type="checkbox" id="wphm_enable_debug_display" name="wphm-options[enable_debug_display]" <?php checked( $wphm_options['enable_debug_display']); ?> value="1"/>
						<label for="wphm_enable_debug_display">
							<?php printf (
								__( 'Enable %s', 'wphm' ),
								'<a href="https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/#wp_debug_display" rel="noopener noreferrer" target="_blank">WP_DEBUG_DISPLAY</a>'
							); ?>
						</label>
						<br />
						<input type="checkbox" id="wphm_enable_debug_log" name="wphm-options[enable_debug_log]" <?php checked( $wphm_options['enable_debug_log']); ?> value="1"/>
						<label for="wphm_enable_debug_log">
							<?php printf (
								__( 'Enable %s', 'wphm' ),
								'<a href="https://developer.wordpress.org/advanced-administration/debug/debug-wordpress/#wp_debug_log" rel="noopener noreferrer" target="_blank">WP_DEBUG_LOG</a>'
							); ?>
						</label>
					</td>
				</tr>
			</table>
			<?php submit_button( __( 'Save changes', 'wphm' ) ); ?>
		</form>
		<br />
		<br />
		<br />
		<span class="wphm-footer"><img src="<?php echo plugins_url( 'assets/img/united-works-logo-teal-plugin.png', __FILE__ ); ?>"></span>
	</div>
	<?php
	}


    public function wphm_sanitize( array $wphm_options ) {
		$wphm_defaults = $this->wphm_get_defaults();
		foreach ( $wphm_options as $wphm_key => $wphm_value ) {
			if ( !isset( $wphm_defaults[$wphm_key] ) || $wphm_value != 1 ) {
				unset( $wphm_options[$wphm_key] );
			}
		}
        return $wphm_options;
    }
	
	
	public function wphm_activation() {
		if ( !get_option( 'wphm_options' ) ) {
			add_option( 'wphm_options', array(
				'limit_login_attempts'	=> true,
				'show_logo_managed'		=> true
			));
			add_option( 'wphm_blacklisted_ips', array() );
		}
	}


	/**
	 * Put together the default options for the plugin options.
	 */
	public function wphm_get_defaults() {
		return array(
			'limit_login_attempts'		=> false,
			'show_logo_managed'			=> false,
			'disable_admin_notices'		=> false,
			'hide_core_updates'			=> false,
			'hide_themes_updates'		=> false,
			'hide_plugins_updates'		=> false,
			'disable_automatic_updates'	=> false,
			'show_staging_warning'		=> false,
			'enable_debugging_mode'		=> false,
			/* Constant "WP_DEBUG_DISPLAY" gets initialized to "true",
			 * by default, if not defined in wp-config.php. */
			'enable_debug_display'		=> true,
			'enable_debug_log'			=> false,
		);
	}


	/*
	 * limit_login_attempts
	 */
  
	 
	public function wphm_auth_signon( $user, $username, $password ) {
		if ( isset( $this->options['limit_login_attempts'] ) ) {
			$blacklisted = (array) get_option( 'wphm_blacklisted_ips' );
			$user_ip = $this->wphm_get_ip();
			if ( isset( $user_ip ) ) {
				if ( isset( $blacklisted[$user_ip] ) ) {
					if ( $blacklisted[$user_ip]['attempts'] >= 3) {
						$duration = $blacklisted[$user_ip]['duration'];
						if ( $duration > time() ) {
							return new WP_Error( 'formangeforsok', sprintf( __( '<strong>Warning</strong>: You have tried to many times, you can try again in %1$s.', 'wphm' ) , $this->wphm_tidsbeg( $duration ) ) . sprintf( '<br><a href="https://unitedworks.no/?utm_source=wordpress-login&utm_medium=login&utm_campaign=support">%s</a>', __( 'Support', 'wphm') ) );
						}
					}
				}
			}
		}
		return $user;
	}


	public function wphm_logfeil( $username ) {
		if ( isset( $this->options['limit_login_attempts'] ) ) {
			$blacklisted = (array) get_option( 'wphm_blacklisted_ips' );
			$user_ip = $this->wphm_get_ip();
			if ( isset( $user_ip ) ) {
				if ( empty($blacklisted[$user_ip]) ) {
					$blacklisted[$user_ip]['attempts'] = 1;
				}
				else {
					$blacklisted[$user_ip]['attempts'] += 1;
				}
				if ( $blacklisted[$user_ip]['attempts'] >= 3 ) {
					$blacklisted[$user_ip]['duration'] = time() + 300;
					if ( $blacklisted[$user_ip]['attempts'] == 3 ) {
						wp_redirect( $_SERVER['HTTP_REFERER'] ); 
					}
				}
				update_option( 'wphm_blacklisted_ips', $blacklisted );
			}
		}
	}


	public function wphm_remove_user_ip() {
		if ( isset( $this->options['limit_login_attempts'] ) ) {
			$blacklisted = (array) get_option( 'wphm_blacklisted_ips' );
			$user_ip = $this->wphm_get_ip();
			if ( isset( $user_ip ) ) {
				if ( isset( $blacklisted[$user_ip] ) ) {
					unset($blacklisted[$user_ip]);
					update_option( 'wphm_blacklisted_ips', $blacklisted );
				}
			}
		}
	}


	public function wphm_get_ip() {
		if ( isset( $_SERVER['HTTP_X_REAL_IP'] ) ) { 
			$ip = $_SERVER['HTTP_X_REAL_IP'];
		} elseif ( isset( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
		} elseif ( isset( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		} elseif ( isset( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = $_SERVER['REMOTE_ADDR'];
		}
		if (filter_var($ip, FILTER_VALIDATE_IP) !== false) {
			return $ip;
		}
		return false;
	}


	public function wphm_tidsbeg($timestamp) {

	   $periods = array(
			array( __("second", "wphm"), __("seconds", "wphm") ),
			array( __("minute", "wphm"), __("minutes", "wphm") ),
			array( __("hour", "wphm"), __("hours", "wphm") ),
			array( __("day", "wphm"), __("days", "wphm") ),
			array( __("week", "wphm"), __("weeks", "wphm") ),
			array( __("month", "wphm"), __("months", "wphm") ),
			array( __("year", "wphm"), __("years", "wphm") )
		);
		$lengths = array(
			"60",
			"60",
			"24",
			"7",
			"4.35",
			"12"
		);
		$current_timestamp = time();
		$difference = abs($current_timestamp - $timestamp);
		for ($i = 0; $difference >= $lengths[$i] && $i < count($lengths) - 1; $i ++) {
			$difference /= $lengths[$i];
		}
		$difference = round($difference);
		if (isset($difference)) {
			return $difference." ".( $difference == 1 ? $periods[$i][0] : $periods[$i][1] );
		}
	}


	/*
	 * show_logo_managed
	 */
	 
	 	 
	public function wphm_frontlogoen() {
		if ( isset( $this->options['show_logo_managed'] ) ) {
	?> 
	<style type="text/css"> 
		body.login div#login h1 a {
			background-image: url(<?php echo plugins_url( 'assets/img/loginmerke.svg', __FILE__ ); ?>);
		}
	</style>
	<?php 
		}
	}


	public function wphm_loginlogo_url( $url ) {
		return 'https://unitedworks.no';
	}


	public function wphm_login_init_action(){
		if ( isset( $this->options['show_logo_managed'] ) ) {
			add_filter( 'login_headerurl',  array( $this, 'wphm_loginlogo_url' ) );
		}
	}
	 
	 
	/*
	 * disable_admin_notices
	 */
	 
	 
	public function wphm_disable_admin_notices() {
		if ( isset( $this->options['disable_admin_notices'] ) ) {
			global $wp_filter;
			if ( is_user_admin() ) {
				if ( isset( $wp_filter['user_admin_notices'] ) ) {
					unset( $wp_filter['user_admin_notices'] );
				}
			} elseif ( isset( $wp_filter['admin_notices'] ) ) {
				unset( $wp_filter['admin_notices'] );
			}
			if ( isset( $wp_filter['all_admin_notices'] ) ) {
				unset( $wp_filter['all_admin_notices'] );
			}
		}
	} 
	 
	 	 	 
	/*
	 * hide_core_updates
	 * hide_themes_updates
	 * hide_plugins_updates
	 */ 
		 
	 
	public function wphm_hide_update_notifications() {
		if ( isset( $this->options['hide_core_updates'] ) ) {
			add_filter( 'pre_site_transient_update_core', array( $this, 'wphm_disable_update_notifications' ) );
		}
		if ( isset( $this->options['hide_themes_updates'] ) ) {
			add_filter( 'pre_site_transient_update_themes', array( $this, 'wphm_disable_update_notifications' ) );
		}
		if ( isset( $this->options['hide_plugins_updates'] ) ) {
			add_filter( 'pre_site_transient_update_plugins', array( $this, 'wphm_disable_update_notifications' ) ); 
		}
	}


	public function wphm_disable_update_notifications() {
		global $wp_version;
		return (object) array( 'last_checked' => time(), 'version_checked' => $wp_version, );
	} 


	/*
	 * disable_automatic_updates
	 */


	public function wphm_disable_auto_updates() {
		if ( isset( $this->options['disable_automatic_updates'] ) ) {
			add_filter( 'automatic_updater_disabled', '__return_true' );
			add_filter( 'wp_auto_update_core', '__return_false' );
			add_filter( 'auto_update_core', '__return_false' );
			add_filter( 'auto_update_theme', '__return_false' );
			add_filter( 'auto_update_plugin', '__return_false' );
			add_filter( 'auto_update_translation', '__return_false' );
		}
	}


	/*
	 * show_staging_warning
	 * enable_debugging_mode warning
	 */


	public function wphm_set_staging_warning() {
		?>
		<div class="notice notice-warning is-dismissible"><p><?php _e( 'You are now editing in Staging', 'wphm' ); ?></p></div>
		<?php
	}


	public function wphm_set_debug_warning() {
		?>
		<div class="notice notice-warning is-dismissible"><p><?php _e( 'Debugging mode enabled', 'wphm' ); ?></p></div>
		<?php
	}


	public function wphm_set_admin_notices() {
		if ( isset( $this->options['show_staging_warning'] ) ) {
			if( strpos( $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], 'staging' ) !== false ){
				add_action( 'admin_notices', array( $this, 'wphm_set_staging_warning' ) );
			}
		}
		if ( isset( $this->options['enable_debugging_mode'] ) ) {
			add_action( 'admin_notices', array( $this, 'wphm_set_debug_warning' ) );
		}
	}


	/**
	 * Check the status of the plugin debug configs and
	 * enable/disable the corresponding WP constants, accordingly.
	 */
	public function wphm_check_wp_debug_define() {
		// Check the config for "WP_DEBUG".
		if ( isset( $this->options['enable_debugging_mode'] ) ) {
			$this->wphm_set_debugging_status( true );
		} else {
			$this->wphm_set_debugging_status();
		}

		// Check the config for "WP_DEBUG_DISPLAY".
		if ( isset( $this->options['enable_debug_display'] ) ) {
			$this->wphm_set_debug_display_status( true );
		} else {
			$this->wphm_set_debug_display_status();
		}

		// Check the config for "WP_DEBUG_LOG".
		if ( isset( $this->options['enable_debug_log'] ) ) {
			$this->wphm_set_debug_log_status( true );
		} else {
			$this->wphm_set_debug_log_status();
		}
	}


	/**
	 * Perform actions on plugin deactivation.
	 */
	public function wphm_deactivation() {
		// Disable all the debug constants.
		$this->wphm_set_debugging_status();
		$this->wphm_set_debug_display_status();
		$this->wphm_set_debug_log_status();
	}


	/**
	 * Set the value for a constant. The name of
	 * the define constant gets passed as an
	 * argument of the method call.
	 * 
	 * @param string $constant_name The name to use for the constant
	 * @param boolean $turn_on The value to use for the constant
	 */
	private function wphm_set_constant_define( $constant_name, $turn_on ) {
		// Don't enable if the contant already defined and enabled. 
		if ( $turn_on
			&& defined( $constant_name )
			&& constant( $constant_name )
		) {
			return;
		}

		// Don't disable if the constant either not defined or defined but disabled.
		if ( ! $turn_on
			&& ! ( defined( $constant_name ) && constant( $constant_name ) )
		) {
			return;
		}

		// Fetch the path to the wp_config.php file.
		$config_file_path = $this->wphm_get_wpconfig_path();
		
		if ( ! $config_file_path ) {
			return;
		}

		$config_file = file( $config_file_path );

		$constant_definition_exists = false;
		
		$turn_on = ( $turn_on ) ? 'true' : 'false';

		$constant = "define( '{$constant_name}', {$turn_on} );". "\r\n";

		foreach ( $config_file as &$line ) {
			if ( ! preg_match( '/^define\(\s*\'([A-Z_]+)\',(.*)\)/', $line, $match ) ) {
				continue;
			}

			if ( $match[1] == $constant_name ) {
				$constant_definition_exists = true;
				$line = $constant;
			}
		}

		// Variable no longer needed, discard it.
		unset( $line );

		/* If the constant definition doesn't exist,
		 * add it to the beginning of the config file. */
		if ( ! $constant_definition_exists ) {
			array_shift( $config_file );
			array_unshift( $config_file, "<?php\r\n", $constant );
		}

		$handle = @fopen( $config_file_path, 'w' );
		foreach( $config_file as $line ) {
			@fwrite( $handle, $line );
		}

		@fclose( $handle );

		$chmod = defined( 'FS_CHMOD_FILE' ) ? FS_CHMOD_FILE : 0644;
		@chmod( $config_file_path, $chmod );
	}


	/**
	 * Set the value for for the "WP_DEBUG" constant.
	 *
	 * @param boolean $turn_on The value to set for the constant
	 */
	private function wphm_set_debugging_status( $turn_on = false ) {
		$this->wphm_set_constant_define( 'WP_DEBUG', $turn_on );
	}


	/**
	 * Set the value for for the "WP_DEBUG_DISPLAY" constant.
	 *
	 * @param boolean $turn_on The value to set for the constant
	 */
	private function wphm_set_debug_display_status( $turn_on = false ) {
		$this->wphm_set_constant_define( 'WP_DEBUG_DISPLAY', $turn_on );
	}


	/**
	 * Set the value for for the "WP_DEBUG_LOG" constant.
	 *
	 * @param boolean $turn_on The value to set for the constant
	 */
	private function wphm_set_debug_log_status( $turn_on = false ) {
		$this->wphm_set_constant_define( 'WP_DEBUG_LOG', $turn_on );
	}


	private function wphm_get_wpconfig_path() {
		$config_file     = ABSPATH . 'wp-config.php';
		$config_file_alt = dirname( ABSPATH ) . '/wp-config.php';

		if ( file_exists( $config_file ) && is_writable( $config_file ) ) {
			return $config_file;
		} elseif ( @file_exists( $config_file_alt ) && is_writable( $config_file_alt ) && ! file_exists( dirname( ABSPATH ) . '/wp-settings.php' ) ) {
			return $config_file_alt;
		}
		return false;
	}

}

$WPHotellManaged = new WPHotellManaged();
