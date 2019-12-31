<?php
/**
* Plugin Name: 				Speed Booster Pack
* Plugin URI: 				https://wordpress.org/plugins/speed-booster-pack/
* Description: 				Speed optimization is vital for SEO. Optimize your PageSpeed scores today!
* Author: 					Optimocha
* Version: 					3.8.4.3
* Author URI: 				https://optimocha.com/
* License: 					GPLv3 or later
* License URI:         		https://www.gnu.org/licenses/gpl-3.0.html
* Requires PHP: 	    	5.6
* Text Domain : 			speed-booster-pack
* Domain Path: 				/lang
*
* Copyright 2015-2017 					Tiguan 				office@tiguandesign.com
* Copyright 05/05/2017 - 10/04/2017 	ShortPixel			alex@shortpixel.com
* Copyright 2017-2019 					MachoThemes 		office@machothemes.com
* Copyright 2019-						Optimocha			baris@optimocha.com
*
* Original Plugin URI: 		https://tiguan.com/speed-booster-pack/
* Original Author URI: 		https://tiguan.com
* Original Author: 			https://profiles.wordpress.org/tiguan/
*
*
* This program is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License, version 3, as
* published by the Free Software Foundation.
*
* This program is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with this program; if not, write to the Free software
* Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
*/

// Security control for vulnerability attempts
if( !defined( 'ABSPATH' ) ) {
	die;
}

/*----------------------------------------------------------------------------------------------------------
	Define some useful plugin constants
-----------------------------------------------------------------------------------------------------------*/

define( 'SPEED_BOOSTER_PACK_PATH', plugin_dir_path( __FILE__ ) );
define( 'SPEED_BOOSTER_PACK_URL',  preg_replace('#^https?:#', '', plugin_dir_url( __FILE__ ) ) );
define( 'SPEED_BOOSTER_PACK_VERSION', '3.8.4.3' );
// Defining css position
define( 'SBP_FOOTER', 10 );
// Defining css last position
define( 'SBP_FOOTER_LAST', 99999 );

/*----------------------------------------------------------------------------------------------------------
	Global Variables
-----------------------------------------------------------------------------------------------------------*/

$sbp_options = get_option( 'sbp_settings' );    // retrieve the plugin settings from the options table

/*----------------------------------------------------------------------------------------------------------
	Main Plugin Class
-----------------------------------------------------------------------------------------------------------*/

if ( ! class_exists( 'Speed_Booster_Pack' ) ) {

	class Speed_Booster_Pack {

		/*----------------------------------------------------------------------------------------------------------
			Function Construct
		-----------------------------------------------------------------------------------------------------------*/

		public function __construct() {
			global $sbp_options;

			// Enqueue admin scripts
			add_action( 'admin_enqueue_scripts', array( $this, 'sbp_admin_enqueue_scripts' ) );

			// load plugin textdomain
			add_action( 'plugins_loaded', array( $this, 'sbp_load_translation' ) );

			add_action( 'admin_notices', array( &$this, 'sbp_display_notices' ) );
			add_action( 'wp_ajax_sbp_dismiss_notices', array( &$this, 'sbp_dismiss_notices' ) );

			// Load plugin settings page
			require_once( SPEED_BOOSTER_PACK_PATH . 'inc/settings.php' );
			$Speed_Booster_Pack_Options = new Speed_Booster_Pack_Options();

			// Load main plugin functions
			require_once( SPEED_BOOSTER_PACK_PATH . 'inc/core.php' );
			$Speed_Booster_Pack_Core = new Speed_Booster_Pack_Core();

			// Enqueue admin style
			add_action( 'admin_enqueue_scripts', array( $this, 'sbp_enqueue_styles' ) );


			// Filters
			$this->path = plugin_basename( __FILE__ );
			add_filter( "plugin_action_links_$this->path", array( $this, 'sbp_settings_link' ) );


		}    // END public function __construct

		/*----------------------------------------------------------------------------------------------------------
			Load plugin textdomain
		-----------------------------------------------------------------------------------------------------------*/

		function sbp_load_translation() {
			load_plugin_textdomain( 'speed-booster-pack', false, SPEED_BOOSTER_PACK_PATH . '/lang/' );
		}


		/*----------------------------------------------------------------------------------------------------------
			Display/dismiss admin notices if needed
		-----------------------------------------------------------------------------------------------------------*/

		function sbp_display_notices() {
			if ( ! get_option( 'sbp_news' ) ) {
				global $sbp_settings_page;
				$screen = get_current_screen();
				if ( $screen->id != $sbp_settings_page ) {
					require_once( SPEED_BOOSTER_PACK_PATH . 'inc/template/notice.php' );
				}
			}
		}

		function sbp_dismiss_notices() {
			update_option( 'sbp_news', true );

			return json_encode( array( "Status" => 0 ) );
		}

		/*----------------------------------------------------------------------------------------------------------
			Activate the plugin
		-----------------------------------------------------------------------------------------------------------*/

		public static function sbp_activate() {
			$sbp_options     = get_option( 'sbp_settings', '' );

			$url      = get_site_url();
			$response = wp_remote_get( $url, array() );

			$get_enqueued_scripts_handle = get_option( 'all_theme_scripts_handle' );
			$get_enqueued_scripts_src    = get_option( 'all_theme_scripts_src' );
			$get_enqueued_styles_handle  = get_option( 'all_theme_styles_handle' );

			if ( get_option( 'all_theme_scripts_handle' ) == '' ) {
				update_option( 'all_theme_scripts_handle', $get_enqueued_scripts_handle );
			}

			if ( get_option( 'all_theme_scripts_src' ) == '' ) {
				update_option( 'all_theme_scripts_src', $get_enqueued_scripts_src );
			}

			if ( get_option( 'all_theme_styles_handle' ) == '' ) {
				update_option( 'all_theme_styles_handle', $get_enqueued_styles_handle );
			}

		} // END public static function sb_activate


		/*----------------------------------------------------------------------------------------------------------
			Deactivate the plugin
		-----------------------------------------------------------------------------------------------------------*/

		public static function sbp_deactivate() {
		}


		/*----------------------------------------------------------------------------------------------------------
			CSS style of the plugin options page
		-----------------------------------------------------------------------------------------------------------*/

		function sbp_enqueue_styles( $hook ) {

			// load stylesheet only on plugin options page
			global $sbp_settings_page;
			if ( $hook != $sbp_settings_page ) {
				return;
			}
			wp_enqueue_style( 'sbp-styles', plugin_dir_url( __FILE__ ) . 'css/style.css', null, SPEED_BOOSTER_PACK_VERSION );//SPEED_BOOSTER_PACK_VERSION );
			//wp_enqueue_style( 'jquery-ui', plugin_dir_url( __FILE__ ) . 'css/vendors/jquery-ui/jquery-ui.min.css' );

		}    //	End function sbp_enqueue_styles


		/*----------------------------------------------------------------------------------------------------------
			Enqueue admin scripts to plugin options page
		-----------------------------------------------------------------------------------------------------------*/

		public function sbp_admin_enqueue_scripts( $hook_sbp ) {
			// load scripts only on plugin options page
			global $sbp_settings_page;
			if ( $hook_sbp != $sbp_settings_page ) {
				return;
			}
			wp_enqueue_script( 'jquery-ui-slider' );
			wp_enqueue_script( 'postbox' );

			wp_enqueue_script( 'sbp-admin-scripts', plugins_url( 'inc/js/admin-scripts.js', __FILE__ ), array(
				'jquery',
				'postbox',
				'jquery-ui-slider',
			), SPEED_BOOSTER_PACK_VERSION, true );

			wp_enqueue_script( 'sbp-plugin-install', plugins_url( 'inc/js/plugin-install.js', __FILE__ ), array(
				'jquery',
				'updates',
			), SPEED_BOOSTER_PACK_VERSION, true );

		}


		/*----------------------------------------------------------------------------------------------------------
			Add settings link on plugins page
		-----------------------------------------------------------------------------------------------------------*/

		function sbp_settings_link( $links ) {
            $pro_link = ' <a href="https://optimocha.com/?ref=sbp" target="_blank">Pro Help</a > ';
			$settings_link = ' <a href="admin.php?page=sbp-options">Settings</a > ';
			array_unshift( $links, $settings_link );
            array_unshift( $links, $pro_link );

			return $links;

		}    //	End function sbp_settings_link
	}//	End class Speed_Booster_Pack
}    //	End if (!class_exists("Speed_Booster_Pack")) (1)

if ( class_exists( 'Speed_Booster_Pack' ) ) {

	// Installation and uninstallation hooks
	register_activation_hook( __FILE__, array( 'Speed_Booster_Pack', 'sbp_activate' ) );
	register_deactivation_hook( __FILE__, array( 'Speed_Booster_Pack', 'sbp_deactivate' ) );

	// instantiate the plugin class
	$speed_booster_pack = new Speed_Booster_Pack();

}    //	End if (!class_exists("Speed_Booster_Pack")) (2)

// make sure to update the path to where you cloned the projects to!

//review function
function sb_pack_check_for_review() {
    if ( ! is_admin() ) {
        return;
    }
    require_once SPEED_BOOSTER_PACK_PATH . 'inc/class-sb-pack-review.php';

    SB_Pack_Review::get_instance( array(
        'slug' => 'speed-booster-pack',
    ) );
}

sb_pack_check_for_review();
