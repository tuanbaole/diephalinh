<?php

// Security control for vulnerability attempts
if( !defined( 'ABSPATH' ) ) {
	die;
}

/*--------------------------------------------------------------------------------------------------------
	Plugin Core Functions
---------------------------------------------------------------------------------------------------------*/

if ( ! class_exists( 'Speed_Booster_Pack_Core' ) ) {

	class Speed_Booster_Pack_Core {
		private $local_cache_dir = '/cache/';
		private $remote_ga_script = 'https://www.google-analytics.com/analytics.js';
		private $local_ga_script;

		public function __construct() {

			global $sbp_options;

			$this->local_ga_script = $this->local_cache_dir . 'speed-booster/analytics.js';

			add_action( 'wp_enqueue_scripts', array( $this, 'sbp_move_scripts_to_footer' ) );

			if ( ! is_admin() and isset( $sbp_options[ 'jquery_to_footer' ] ) ) {
				add_action( 'wp_head', array( $this, 'sbp_scripts_to_head' ) );
			}

			add_action( 'after_setup_theme', array( $this, 'sbp_junk_header_tags' ) );
			add_action( 'init', array( $this, 'sbp_init' ) );

			//enable cdn rewrite
			if( isset( $sbp_options[ 'sbp_enable_cdn' ] ) && $sbp_options[ 'sbp_enable_cdn' ] == "1" && isset( $sbp_options[ 'sbp_cdn_url' ] ) ) {
				add_action( 'template_redirect', array( $this, 'sbp_cdn_rewrite' ) );
			}

			//enable lazy loading
			if( isset( $sbp_options[ 'sbp_enable_lazy_load' ] ) ) {
				add_action( 'template_redirect', array( $this, 'sbp_lazy_load' ) );
				add_action( 'wp_enqueue_scripts', array( $this, 'sbp_lazy_load_script' ) );
				add_action( 'enqueue_embed_scripts', array( $this, 'sbp_lazy_load_script' ) );
			}

			// WooCommerce optimizing features
			if( $this->sbp_is_plugin_active( 'woocommerce/woocommerce.php' ) ) {
				/**
				 * @since 3.8.3
				 */
				if( isset( $sbp_options[ 'dequeue_wc_scripts' ] ) ) {
					add_action( 'wp_enqueue_scripts', array( $this, 'sbp_dequeue_wc_scripts' ) );
				}

				/**
				 * @since 3.8
				 */
				// Disable Cart Fragments
				if( isset( $sbp_options[ 'disable_cart_fragments' ] ) ) {
					add_action( 'wp_enqueue_scripts', array( $this, 'sbp_disable_cart_fragments' ), 999 );
				}

				/**
				 * Disable password strength meter
				 * @since 3.8.3
				 */
				 if( isset( $sbp_options[ 'disable_password_strength_meter' ] ) ) {
					 add_action( 'wp_print_scripts', array( $this, 'sbp_disable_password_strength_meter' ), 100 );
				 }
			}

			// Start GA
			if( isset( $sbp_options[ 'sbp_enable_local_analytics' ] ) && $sbp_options[ 'sbp_enable_local_analytics' ] == "1" ) {

				if( !wp_next_scheduled( 'sbp_update_ga' ) ) {
					wp_schedule_event( time(), 'daily', 'sbp_update_ga' );
				}

				if( isset( $sbp_options[ 'sbp_monsterinsights' ] ) && $sbp_options[ 'sbp_monsterinsights' ] == "1" ) {
					add_filter( 'monsterinsights_frontend_output_analytics_src', WP_CONTENT_URL . $this->local_ga_script, 1000 );
				}
				else {

					if( isset( $sbp_options[ 'sbp_tracking_position' ] ) && $sbp_options[ 'sbp_tracking_position' ] == 'footer' ) {
						$tracking_code_position = 'wp_footer';
					}
					else {
						$tracking_code_position = 'wp_head';
					}
					add_action( $tracking_code_position, array( $this, 'sbp_print_ga' ), 0 );
				}
			}
			else {

				if( wp_next_scheduled( 'sbp_update_ga' ) ) {
					wp_clear_scheduled_hook( 'sbp_update_ga' );
				}
			}

			add_action( 'sbp_update_ga', array( $this, 'sbp_update_ga' ) );
			// End GA

			$this->sbp_css_optimizer(); // CSS Optimizer functions

			// Minifier
			if ( ! is_admin() and isset( $sbp_options[ 'minify_html_js' ] ) ) {
				$this->sbp_minifier();
			}

			//	Defer parsing of JavaScript
			if ( ! is_admin() and isset( $sbp_options['defer_parsing'] ) ) {
				add_filter( 'script_loader_tag', array( $this, 'sbp_defer_parsing_of_js' ), 10, 3 );
			}

			//	Remove query strings from static resources
			if ( ! is_admin() and isset( $sbp_options[ 'query_strings' ] ) ) {
				add_filter( 'script_loader_src', array( $this, 'sbp_remove_query_strings' ), 15, 1 );
				add_filter( 'style_loader_src', array( $this, 'sbp_remove_query_strings' ), 15, 1 );
			}

			/**
			 * @since 3.7
			 */
			// Disable emojis
			if ( ! is_admin() && isset( $sbp_options[ 'remove_emojis' ] ) ) {
				add_action( 'init', array( $this, 'sbp_disable_emojis' ) );
			}

			/**
			 * @since 3.8
			 */
			// Enable Instant Page
			if( isset( $sbp_options[ 'enable_instant_page' ] ) ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'sbp_enable_instant_page' ) );
			}

			// Disable Self Pingbacks
			if ( isset( $sbp_options['disable_self_pingbacks'] ) ) {
				add_action( 'pre_ping', array($this,'sbp_remove_self_ping' ));
			}

			// Remove REST API Links
			if ( isset( $sbp_options['remove_rest_api_links'] ) ) {
				remove_action( 'wp_head', 'rest_output_link_wp_head', 10 );
			}

			//Disable Dash icons
			if ( isset( $sbp_options['disable_dashicons'] ) ) {
				add_action( 'wp_enqueue_scripts', array( $this, 'sbp_disable_dash_icons' ) );
			}

			if ( isset( $sbp_options['disable_google_maps'] ) ) {
				add_action( 'wp_loaded', array( $this, 'sbp_disable_google_maps' ) );
			}

			if (  isset( $sbp_options['disable_heartbeat'] ) ) {
				add_action( 'init', array( $this, 'sbp_disable_heartbeat' ), 1 );
			}

			if ( ! empty( $sbp_options['heartbeat_frequency'] ) ) {
				add_filter( 'heartbeat_settings', array( $this, 'sbp_heartbeat_frequency' ), 1 );
			}

			if ( ! empty( $sbp_options['limit_post_revisions'] ) && ! defined( 'WP_POST_REVISIONS' ) ) {
				define( 'WP_POST_REVISIONS', $sbp_options['limit_post_revisions'] );
			}

			if ( ! empty( $sbp_options['autosave_interval'] ) && ! defined( 'AUTOSAVE_INTERVAL' ) ) {
				define( 'AUTOSAVE_INTERVAL', $sbp_options['autosave_interval'] );
			}

			/**
			 * @since 3.8.1
			 */
			if( ! empty( $sbp_options[ 'remove_jquery_migrate' ] ) ) {
				add_action( 'wp_default_scripts', array( $this, 'sbp_remove_jquery_migrate' ) );
			}

		}  //  END public public function __construct

		/**
		 * Check if a plugin is active or not.
		 * @since 3.8.3
		 */
		function sbp_is_plugin_active( $path ) {
			return in_array( $path, apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) );
		}

		/**
		 * Dequeue WooCommerce scripts on non-WC pages.
		 * @since 3.8.3
		 */
		function sbp_dequeue_wc_scripts() {
			// check for woocommerce is currently inactive
			if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() ) {
				// dequeue WooCommerce styles
				wp_dequeue_style( 'woocommerce_chosen_styles' );
				wp_dequeue_style( 'woocommerce_fancybox_styles' );
				wp_dequeue_style( 'woocommerce_frontend_styles' );
				wp_dequeue_style( 'woocommerce_prettyPhoto_css' );

				// dequeue WooCommerce scripts
				wp_dequeue_script( 'wc-add-to-cart' );
				wp_dequeue_script( 'wc-add-to-cart-variation' );
				wp_dequeue_script( 'wc-cart' );
				wp_dequeue_script( 'wc-cart-fragments' );
				wp_dequeue_script( 'wc-checkout' );
				wp_dequeue_script( 'wc-chosen' );
				wp_dequeue_script( 'wc-single-product' );
				wp_dequeue_script( 'wc-single-product' );
				wp_dequeue_script( 'wc_price_slider' );
				wp_dequeue_script( 'woocommerce' );
			}
		}

		/**
		 * @since 3.8.1
		 */
		/*--------------------------------------------------------------------------------------------------------
					Remove JQuery Migrate
		---------------------------------------------------------------------------------------------------------*/
		function sbp_remove_jquery_migrate( $scripts ) {
			if ( ! is_admin() && isset( $scripts->registered[ 'jquery' ] ) ) {
				$jquery_script = $scripts->registered[ 'jquery' ];

				if ( $jquery_script->deps ) { // Check whether the script has any dependencies
					$jquery_script->deps = array_diff( $jquery_script->deps, array( 'jquery-migrate' ) );
				}
			}
		}

		/*--------------------------------------------------------------------------------------------------------
					Disable Dash icons
		---------------------------------------------------------------------------------------------------------*/
		function sbp_disable_dash_icons() {
			if ( ! is_user_logged_in() ) {
				wp_dequeue_style( 'dashicons' );
				wp_deregister_style( 'dashicons' );
			}
		}

		/*--------------------------------------------------------------------------------------------------------
					Disable Heartbeat
		---------------------------------------------------------------------------------------------------------*/

		function sbp_disable_heartbeat() {
			wp_deregister_script( 'heartbeat' );
		}

		/*--------------------------------------------------------------------------------------------------------
					Heartbeat Frequency
		---------------------------------------------------------------------------------------------------------*/

		function sbp_heartbeat_frequency() {
			global $sbp_options;
			$settings['interval'] = $sbp_options['heartbeat_frequency']; //Anything between 15-120

			return $settings;
		}

		/*--------------------------------------------------------------------------------------------------------
				 Disable Google Maps
		---------------------------------------------------------------------------------------------------------*/

		function sbp_disable_google_maps() {
			ob_start( array( $this, 'sbp_disable_google_maps_regex' ) );
		}

		function sbp_disable_google_maps_regex( $html ) {
			$html = preg_replace( '/<script[^<>]*\/\/maps.(googleapis|google|gstatic).com\/[^<>]*><\/script>/i', '', $html );

			return $html;
		}

		/*--------------------------------------------------------------------------------------------------------
			 Disable Password Strength Meter
			---------------------------------------------------------------------------------------------------------*/

		function sbp_disable_password_strength_meter() {
			global $wp;

			$wp_check = isset( $wp->query_vars['lost-password'] ) || ( isset( $_GET['action'] ) && $_GET['action'] === 'lostpassword' ) || is_page( 'lost_password' );

			$wc_check = ( ( is_account_page() || is_checkout() ) );

			if ( ! $wp_check && ! $wc_check ) {
				if ( wp_script_is( 'zxcvbn-async', 'enqueued' ) ) {
					wp_dequeue_script( 'zxcvbn-async' );
				}

				if ( wp_script_is( 'password-strength-meter', 'enqueued' ) ) {
					wp_dequeue_script( 'password-strength-meter' );
				}

				if ( wp_script_is( 'wc-password-strength-meter', 'enqueued' ) ) {
					wp_dequeue_script( 'wc-password-strength-meter' );
				}
			}
		}

		/*--------------------------------------------------------------------------------------------------------
			Init the CSS Optimizer actions
		---------------------------------------------------------------------------------------------------------*/

		function sbp_init() {

			global $sbp_options;

			if ( ! is_admin() and isset( $sbp_options['sbp_css_async'] ) ) {
				add_action( 'wp_print_styles', array( $this, 'sbp_print_styles' ), SBP_FOOTER );
				add_action( 'wp_footer', array( $this, 'sbp_print_delayed_styles' ), SBP_FOOTER + 1 );
			}

		}



		/*--------------------------------------------------------------------------------------------------------
			ACTION wp_print_styles
		---------------------------------------------------------------------------------------------------------*/

		function sbp_print_styles() {
			global $sbp_styles_are_async;
			global $sbp_styles;
			global $sbp_options;

			if ( isset( $sbp_options['sbp_css_minify'] ) ) {
				$minify = true;
			} else {
				$minify = false;
			}

			$sbp_styles_are_async = true;

			$sbp_styles = sbp_generate_styles_list();

			if ( ! isset( $sbp_options['sbp_footer_css'] ) ) {

				$not_inlined = array();

				foreach ( $sbp_styles as $style ) {
					echo "<style type=\"text/css\" " . ( $style['media'] ? "media=\"{$style['media']}\"" : '' ) . ">";
					if ( ! sbp_inline_css( $style['src'], $minify ) ) {
						$not_inlined[] = $style;
					}
					echo "</style>";
				}
				if ( ! empty( $not_inlined ) ) {
					foreach ( $not_inlined as $style ) {
						?>
						<link rel="stylesheet" href="<?php echo $style['src'] ?>"
							  type="text/css" <?php echo $style['media'] ? "media=\"{$style['media']}\"" : '' ?> /><?php
					}
				}
			}

			sbp_unregister_styles();
		}


		/*--------------------------------------------------------------------------------------------------------
			ACTION wp_footer
		---------------------------------------------------------------------------------------------------------*/

		function sbp_print_delayed_styles() {
			global $sbp_styles;
			global $sbp_options;

			if ( isset( $sbp_options['sbp_css_minify'] ) ) {
				$minify = true;
			} else {
				$minify = false;
			}

			if ( isset( $sbp_options['sbp_footer_css'] ) ) {

				$not_inlined = array();
				foreach ( $sbp_styles as $style ) {
					echo "<style type=\"text/css\" " . ( $style['media'] ? "media=\"{$style['media']}\"" : '' ) . ">";
					if ( ! sbp_inline_css( $style['src'], $minify ) ) {
						$not_inlined[] = $style;
					}
					echo "</style>";
				}
				if ( ! empty( $not_inlined ) ) {
					foreach ( $not_inlined as $style ) {
						?>
						<link rel="stylesheet" href="<?php echo $style['src'] ?>"
							  type="text/css" <?php echo $style['media'] ? "media=\"{$style['media']}\"" : '' ?> /><?php
					}
				}
			}
		}


		/*--------------------------------------------------------------------------------------------------------
			Moves scripts to the footer to decrease page load times, while keeping stylesheets in the header
		---------------------------------------------------------------------------------------------------------*/

		function sbp_move_scripts_to_footer() {

			global $sbp_options;

			if ( ! is_admin() and isset( $sbp_options['jquery_to_footer'] ) ) {

				remove_action( 'wp_head', 'wp_print_scripts' );
				remove_action( 'wp_head', 'wp_print_head_scripts', 9 );
				remove_action( 'wp_head', 'wp_enqueue_scripts', 1 );

			}

		}	//  END function sbp_move_scripts_to_footer

		/*--------------------------------------------------------------------------------------------------------
			Put scripts back to the head
		---------------------------------------------------------------------------------------------------------*/

		public function sbp_scripts_to_head() {

			/**
			 * Default: add jQuery to header always
			 *
			 * @since 3.7
			 */
			global $wp_scripts;
			$js_footer_exceptions1 = '';
			$js_footer_exceptions2 = '';
			$js_footer_exceptions3 = '';
			$js_footer_exceptions4 = '';

			if ( get_option( 'sbp_js_footer_exceptions1' ) ) {
				$js_footer_exceptions1 = get_option( 'sbp_js_footer_exceptions1' );
			}

			if ( get_option( 'sbp_js_footer_exceptions2' ) ) {
				$js_footer_exceptions2 = get_option( 'sbp_js_footer_exceptions2' );
			}

			if ( get_option( 'sbp_js_footer_exceptions3' ) ) {
				$js_footer_exceptions3 = get_option( 'sbp_js_footer_exceptions3' );
			}

			if ( get_option( 'sbp_js_footer_exceptions4' ) ) {
				$js_footer_exceptions4 = get_option( 'sbp_js_footer_exceptions4' );
			}

			$sbp_enq  = 'enqueued';
			$sbp_reg  = 'registered';
			$sbp_done = 'done';

			/**
			 * Echo jQuery in header all the time, if none of the other options contain in
			 *
			 * @since 3.7
			 *
			 * New solution, going forward so not to crash so many sites anymore
			 *
			 *		This should come BEFORE the fallback function, since jQuery should be ALWAYS
			 *		the first loaded script.
			 *
			 */
			if ( $js_footer_exceptions1 !== 'jquery-core' || $js_footer_exceptions2 !== 'jquery-core' || $js_footer_exceptions3 !== 'jquery-core' || $js_footer_exceptions4 !== 'jquery-core' ) {

				// if the script actually exists, dequeue it and re-add it for header inclusion
				$script_src = $wp_scripts->registered['jquery-core']->src;

				if ( strpos( $script_src, 'wp-includes' ) == true ) { // it's a local resource, append wordpress installation URL
					echo '<script type="text/javascript" src="' . get_site_url() . esc_attr( $script_src ) . '"></script>';
				} else {
					echo '<script type="text/javascript" src="' . esc_attr( $script_src ) . '"></script>';
				}

				// deregister & dequeue the script
				//wp_deregister_script( 'jquery-core' );
				//wp_dequeue_script( 'jquery-core' );
				$wp_scripts->registered[ 'jquery-core' ]->src = null;
			}


			/**
			 * Echo the scripts in the header
			 *
			 * @since 3.7
			 *
			 * Fallback for previous plugin users
			 *
			 */
			if ( array_key_exists( $js_footer_exceptions1, $wp_scripts->registered ) ) {
				$script_src = '';
				// if the script actually exists, dequeue it and re-add it for header inclusion
				$script_src = $wp_scripts->registered[ $js_footer_exceptions1 ]->src;

				if ( strpos( $script_src, 'wp-includes' ) == true ) { // it's a local resource, append wordpress installation URL
					echo '<script type="text/javascript" src="' . get_site_url() . esc_attr( $script_src ) . '"></script>';
				} else {
					echo '<script type="text/javascript" src="' . esc_attr( $script_src ) . '"></script>';
				}
			}

			if ( array_key_exists( $js_footer_exceptions2, $wp_scripts->registered ) ) {
				$script_src = '';
				// if the script actually exists, dequeue it and re-add it for header inclusion
				$script_src = $wp_scripts->registered[ $js_footer_exceptions2 ]->src;

				if ( strpos( $script_src, 'wp-includes' ) == true ) {
					echo '<script type="text/javascript" src="' . get_site_url() . esc_attr( $script_src ) . '"></script>';
				} else {
					echo '<script type="text/javascript" src="' . esc_attr( $script_src ) . '"></script>';
				}
			}

			if ( array_key_exists( $js_footer_exceptions3, $wp_scripts->registered ) ) {
				$script_src = '';
				// if the script actually exists, dequeue it and re-add it for header inclusion
				$script_src = $wp_scripts->registered[ $js_footer_exceptions3 ]->src;

				if ( strpos( $script_src, 'wp-includes' ) == true ) {
					echo '<script type="text/javascript" src="' . get_site_url() . esc_attr( $script_src ) . '"></script>';
				} else {
					echo '<script type="text/javascript" src="' . esc_attr( $script_src ) . '"></script>';
				}

			}

			if ( array_key_exists( $js_footer_exceptions4, $wp_scripts->registered ) ) {
				$script_src = '';
				// if the script actually exists, dequeue it and re-add it for header inclusion
				$script_src = $wp_scripts->registered[ $js_footer_exceptions4 ]->src;

				if ( strpos( $script_src, 'wp-includes' ) == true ) { // it's a local resource, append wordpress installation URL
					echo '<script type="text/javascript" src="' . get_site_url() . esc_attr( $script_src ) . '"></script>';
				} else {
					echo '<script type="text/javascript" src="' . esc_attr( $script_src ) . '"></script>';
				}
			}


			/**
			 * De-register the scripts from other parts of the site since they're already echo-ed in the header
			 */
			/*--------------------------------------------------------------------------------------------------------*/
			if ( ! empty( $sbp_js_footer_exceptions1 ) and wp_script_is( $js_footer_exceptions1, $sbp_enq ) ) {
				wp_dequeue_script( $js_footer_exceptions1 );
			}
			if ( ! empty( $sbp_js_footer_exceptions2 ) and wp_script_is( $js_footer_exceptions2, $sbp_enq ) ) {
				wp_dequeue_script( $js_footer_exceptions2 );
			}
			if ( ! empty( $sbp_js_footer_exceptions3 ) and wp_script_is( $js_footer_exceptions3, $sbp_enq ) ) {
				wp_dequeue_script( $sbp_js_footer_exceptions3 );
			}
			if ( ! empty( $sbp_js_footer_exceptions4 ) and wp_script_is( $js_footer_exceptions4, $sbp_enq ) ) {
				wp_dequeue_script( $sbp_js_footer_exceptions4 );
			}
			/*--------------------------------------------------------------------------------------------------------*/
			if ( ! empty( $js_footer_exceptions1 ) and wp_script_is( $js_footer_exceptions1, $sbp_reg ) ) {
				wp_deregister_script( $js_footer_exceptions1 );
			}
			if ( ! empty( $js_footer_exceptions2 ) and wp_script_is( $js_footer_exceptions2, $sbp_reg ) ) {
				wp_deregister_script( $js_footer_exceptions2 );
			}
			if ( ! empty( $js_footer_exceptions3 ) and wp_script_is( $js_footer_exceptions3, $sbp_reg ) ) {
				wp_deregister_script( $js_footer_exceptions3 );
			}
			if ( ! empty( $js_footer_exceptions4 ) and wp_script_is( $js_footer_exceptions4, $sbp_reg ) ) {
				wp_deregister_script( $js_footer_exceptions4 );
			}
			/*--------------------------------------------------------------------------------------------------------*/
			if ( ! empty( $js_footer_exceptions1 ) and wp_script_is( $js_footer_exceptions1, $sbp_done ) ) {
				wp_deregister_script( $js_footer_exceptions1 );
			}
			if ( ! empty( $js_footer_exceptions2 ) and wp_script_is( $js_footer_exceptions2, $sbp_done ) ) {
				wp_deregister_script( $js_footer_exceptions2 );
			}
			if ( ! empty( $js_footer_exceptions3 ) and wp_script_is( $js_footer_exceptions3, $sbp_done ) ) {
				wp_deregister_script( $js_footer_exceptions3 );
			}
			if ( ! empty( $js_footer_exceptions4 ) and wp_script_is( $js_footer_exceptions4, $sbp_done ) ) {
				wp_deregister_script( $js_footer_exceptions4 );
			}

		}


		/*--------------------------------------------------------------------------------------------------------
			Minify HTML and Javascripts
		---------------------------------------------------------------------------------------------------------*/

		function sbp_minifier() {

			require_once( SPEED_BOOSTER_PACK_PATH . 'inc/sbp-minifier.php' );
		}	//	End function sbp_minifier()


		/*--------------------------------------------------------------------------------------------------------
			CSS Optimizer
		---------------------------------------------------------------------------------------------------------*/

		function sbp_css_optimizer() {

			require_once( SPEED_BOOSTER_PACK_PATH . 'inc/css-optimizer.php' );

		}	//	End function sbp_css_optimizer()

		/*--------------------------------------------------------------------------------------------------------
			Defer parsing of JavaScript and exclusion files
		---------------------------------------------------------------------------------------------------------*/

		function sbp_defer_parsing_of_js( $tag, $handle, $src ) {

			$defer_exclude1 = '';
			$defer_exclude2 = '';
			$defer_exclude3 = '';
			$defer_exclude4 = '';

			if ( get_option( 'sbp_defer_exceptions1' ) ) {
				$defer_exclude1 = get_option( 'sbp_defer_exceptions1' );
			}

			if ( get_option( 'sbp_defer_exceptions2' ) ) {
				$defer_exclude2 = get_option( 'sbp_defer_exceptions2' );
			}

			if ( get_option( 'sbp_defer_exceptions3' ) ) {
				$defer_exclude3 = get_option( 'sbp_defer_exceptions3' );
			}

			if ( get_option( 'sbp_defer_exceptions4' ) ) {
				$defer_exclude4 = get_option( 'sbp_defer_exceptions4' );
			}

			$array_with_values[] = $defer_exclude1;
			$array_with_values[] = $defer_exclude2;
			$array_with_values[] = $defer_exclude3;
			$array_with_values[] = $defer_exclude4;

			$array_with_values = apply_filters( 'sbp_exclude_defer_scripts', $array_with_values ); // possibility of extending this via filters
			$array_with_values = array_filter( $array_with_values ); // remove empty entries


			if ( ! in_array( $handle, $array_with_values ) ) {
				return '<script src="' . $src . '" defer="defer" type="text/javascript"></script>' . "\n";
			}

			return $tag;

		}	//	END function sbp_defer_parsing_of_js


		/*--------------------------------------------------------------------------------------------------------
			Remove query strings from static resources
		---------------------------------------------------------------------------------------------------------*/

		function sbp_remove_query_strings( $src ) {	//	remove "?ver" string

			$output = preg_split( "/(\?rev|&ver|\?ver)/", $src );

			return $output[0];

		}

		/*--------------------------------------------------------------------------------------------------------
			Disable Emoji
		---------------------------------------------------------------------------------------------------------*/
		function sbp_disable_emojis() {
			remove_action( 'wp_head', 'print_emoji_detection_script', 7 );
			remove_action( 'admin_print_scripts', 'print_emoji_detection_script' );
			remove_action( 'wp_print_styles', 'print_emoji_styles' );
			remove_action( 'admin_print_styles', 'print_emoji_styles' );
			remove_filter( 'the_content_feed', 'wp_staticize_emoji' );
			remove_filter( 'comment_text_rss', 'wp_staticize_emoji' );
			remove_filter( 'wp_mail', 'wp_staticize_emoji_for_email' );

			add_filter( 'tiny_mce_plugins', array( $this, 'sbp_disable_emojis_tinymce' ) );
			add_filter( 'wp_resource_hints', array( $this, 'sbp_disable_emojis_dns_prefetch' ), 10, 2 );
		}

		function sbp_disable_emojis_tinymce( $plugins ) {
			if ( is_array( $plugins ) ) {
				return array_diff( $plugins, array( 'wpemoji' ) );
			} else {
				return array();
			}
		}

		function sbp_disable_emojis_dns_prefetch( $urls, $relation_type ) {
			if ( 'dns-prefetch' == $relation_type ) {
				$emoji_svg_url = apply_filters( 'emoji_svg_url', 'https://s.w.org/images/core/emoji/2.2.1/svg/' );
				$urls		  = array_diff( $urls, array( $emoji_svg_url ) );
			}

			return $urls;
		}

		/*--------------------------------------------------------------------------------------------------------
			Disable Self Pingbacks
		---------------------------------------------------------------------------------------------------------*/

		function sbp_remove_self_ping( &$links ) {

			$home = get_option( 'home' );
			foreach ( $links as $l => $link ) {
				if ( 0 === strpos( $link, $home ) ) {
					unset( $links[ $l ] );
				}
			}

		}

		/*--------------------------------------------------------------------------------------------------------
		Disable Cart Fragments
		---------------------------------------------------------------------------------------------------------*/

		function sbp_disable_cart_fragments() {
			global $wp_scripts;

			$handle = 'wc-cart-fragments';

			$load_cart_fragments_path = $wp_scripts->registered[ $handle ]->src;
			$wp_scripts->registered[ $handle ]->src = null;
			wp_add_inline_script(
				'woocommerce',
				'
				function sbp_getCookie(name) {
					var v = document.cookie.match("(^|;) ?" + name + "=([^;]*)(;|$)");
					return v ? v[2] : null;
				}

				function sbp_check_wc_cart_script() {
				var cart_src = "' . $load_cart_fragments_path . '";
				var script_id = "sbp_loaded_wc_cart_fragments";

					if( document.getElementById(script_id) !== null ) {
						return false;
					}

					if( sbp_getCookie("woocommerce_cart_hash") ) {
						var script = document.createElement("script");
						script.id = script_id;
						script.src = cart_src;
						script.async = true;
						document.head.appendChild(script);
					}
				}

				sbp_check_wc_cart_script();
				document.addEventListener("click", function(){setTimeout(sbp_check_wc_cart_script,1000);});
				'
			);
		}

		/*--------------------------------------------------------------------------------------------------------
		Enable Instant Page
		---------------------------------------------------------------------------------------------------------*/

		function sbp_enable_instant_page() {
			wp_enqueue_script( 'sbp-ins-page', plugins_url( 'js/inspage.js', __FILE__ ), false, '2.0.0', true );
		}

		/*--------------------------------------------------------------------------------------------------------
			Remove junk header tags
		---------------------------------------------------------------------------------------------------------*/

		public function sbp_junk_header_tags() {

			global $sbp_options;

			//	Remove Adjacent Posts links PREV/NEXT
			if ( isset( $sbp_options['remove_adjacent'] ) ) {
				remove_action( 'wp_head', 'adjacent_posts_rel_link_wp_head' );
			}

			//	Remove Windows Live Writer Manifest Link
			if ( isset( $sbp_options['wml_link'] ) ) {
				remove_action( 'wp_head', 'wlwmanifest_link' );
			}

			// Remove RSD (Really Simple Discovery) Link
			if ( isset( $sbp_options['rsd_link'] ) ) {
				remove_action( 'wp_head', 'rsd_link' );
			}

			//	Remove WordPress Shortlinks from WP Head
			if ( isset( $sbp_options['remove_wsl'] ) ) {
				remove_action( 'wp_head', 'wp_shortlink_wp_head' );
			}

			//	Remove WP Generator/Version - for security reasons and cleaning the header
			if ( isset( $sbp_options['wp_generator'] ) ) {
				remove_action( 'wp_head', 'wp_generator' );
			}

			//	Remove all feeds
			if ( isset( $sbp_options['remove_all_feeds'] ) ) {
				remove_action( 'wp_head', 'feed_links_extra', 3 );	// remove the feed links from the extra feeds such as category feeds
				remove_action( 'wp_head', 'feed_links', 2 );		// remove the feed links from the general feeds: Post and Comment Feed
			}

		}	//	END public function sbp_junk_header_tags


		/*--------------------------------
			Lazy Loader (native with polyfill)
		---------------------------------*/
		
		function sbp_lazy_load_script() {
			wp_enqueue_script( 'sbp-lazy-load', plugins_url( 'js/lazyload.js', __FILE__ ), false, '12.1', true );
			wp_add_inline_script( 'sbp-lazy-load', '
				(function() {
					var ll = new LazyLoad({
						elements_selector: "[loading=lazy]",
						use_native: true
					});
				})();
				' );
		}
		
		function sbp_lazy_load() {
			ob_start( array( $this, 'sbp_lazy_loader') );
		}

		function sbp_lazy_loader( $html ) {
			$placeholder = 'data:image/svg+xml,%3Csvg%20xmlns%3D%27http://www.w3.org/2000/svg%27%20viewBox%3D%270%200%203%202%27%3E%3C/svg%3E';
			$html = preg_replace( "/<(img|source|iframe)(.*?) (src=)(.*?)>/i", '<$1$2 $3"' . $placeholder . '" data-$3$4>', $html );
			$html = preg_replace( "/<(img|source|iframe)(.*?) (srcset=)(.*?)>/i", '<$1$2 $3"' . $placeholder . '" data-$3$4>', $html );
			$html = preg_replace( "/<(img|source|iframe)(.*?) ?(\/?)>/i", '<$1$2 loading="lazy" $3>', $html );
			return $html;
		}


		/*--------------------------------
			CDN Rewrite URLs
		---------------------------------*/
		
		function sbp_cdn_rewrite() {
			ob_start( array( $this, 'sbp_cdn_rewriter' ) );
		}

		function sbp_cdn_rewriter($html) {
			global $sbp_options;
			$sbp_cdn_directories = $sbp_options['sbp_cdn_included_directories'];

			//Prep Site URL
			$escapedSiteURL = quotemeta(get_option('home'));
			$regExURL = '(https?:|)' . substr($escapedSiteURL, strpos($escapedSiteURL, '//'));

			//Prep Included Directories
			$directories = 'wp\-content|wp\-includes';
			if(!empty($sbp_cdn_directories)) {
				$directoriesArray = array_map('trim', explode(',', $sbp_cdn_directories));
				if(count($directoriesArray) > 0) {
					$directories = implode('|', array_map('quotemeta', array_filter($directoriesArray)));
				}
			}

			//Rewrite URLs + Return
			$regEx = '#(?<=[(\"\'])(?:' . $regExURL . ')?/(?:((?:' . $directories . ')[^\"\')]+)|([^/\"\']+\.[^/\"\')]+))(?=[\"\')])#';
			$cdnHTML = preg_replace_callback($regEx, array($this,'sbp_cdn_rewrite_url'), $html);
			return $cdnHTML;
		}

		function sbp_cdn_rewrite_url($url) {
			global $sbp_options;
			$sbp_cdn_url = $sbp_options['sbp_cdn_url'];
			$sbp_cdn_excluded = $sbp_options['sbp_cdn_exclusions'];

			//Make Sure CDN URL is Set
			if(!empty($sbp_cdn_url)) {

				//Don't Rewrite if Excluded
				if(!empty($sbp_cdn_excluded)) {
					$exclusions = array_map('trim', explode(',', $sbp_cdn_excluded));
					foreach($exclusions as $exclusion) {
						if(!empty($exclusion) && stristr($url[0], $exclusion) != false) {
							return $url[0];
						}
					}
				}

				//Don't Rewrite if Previewing
				if(is_admin_bar_showing() && isset($_GET['preview']) && $_GET['preview'] == 'true') {
					return $url[0];
				}

				//Prep Site URL
				$siteURL = get_option('home');
				$siteURL = substr($siteURL, strpos($siteURL, '//'));

				//Replace URL w/ No HTTP/S Prefix
				if(strpos($url[0], '//') === 0) {
					return str_replace($siteURL, $sbp_cdn_url, $url[0]);
				}

				//Found Site URL, Replace Non Relative URL w/ HTTP/S Prefix
				if(strstr($url[0], $siteURL)) {
					return str_replace(array('http:' . $siteURL, 'https:' . $siteURL), $sbp_cdn_url, $url[0]);
				}
				//Replace Relative URL
				return $sbp_cdn_url . $url[0];
			}

			//Return Original URL
			return $url[0];
		}

		/*--------------------------------------------
			File processor
		--------------------------------------------*/

		function sbp_file_process() {
			global $wp_filesystem;

			require_once ( ABSPATH . '/wp-admin/includes/file.php' );
			WP_Filesystem();

			return $wp_filesystem;
		}

		/*--------------------------------------------
			Google Analytics
		--------------------------------------------*/

		//update analytics.js
		function sbp_update_ga() {
			$wp_filesystem	= $this->sbp_file_process();

			if( !$wp_filesystem->exists( WP_CONTENT_DIR . $this->local_cache_dir . 'speed-booster/' ) ) {
				$wp_filesystem->mkdir( WP_CONTENT_DIR . $this->local_cache_dir, FS_CHMOD_DIR );
				$wp_filesystem->mkdir( WP_CONTENT_DIR . $this->local_cache_dir . 'speed-booster/', FS_CHMOD_DIR );
			}

			$file_content = $wp_filesystem->get_contents( $this->remote_ga_script );

			if( $wp_filesystem->put_contents( WP_CONTENT_DIR . $this->local_ga_script, $file_content, FS_CHMOD_FILE ) ) return true;

			return false;
		}


		//print analytics script
		function sbp_print_ga() {
			global $sbp_options;

			//dont print for logged in admins
			if( current_user_can( 'manage_options' ) && empty( $sbp_options[ 'sbp_track_loggedin_admins' ] ) ) {
				return;
			}

			if( isset( $sbp_options[ 'sbp_ga_tracking_id' ] ) && !empty( $sbp_options[ 'sbp_ga_tracking_id' ] ) ) {
				$wp_filesystem	= $this->sbp_file_process();
				$ga_script = WP_CONTENT_URL . $this->local_ga_script;

				if( !$wp_filesystem->exists( WP_CONTENT_DIR . $this->local_ga_script ) ) {

					if( !$this->sbp_update_ga() ) {
						$ga_script = $this->remote_ga_script;
					}
				}

				echo "\n\n<!-- Local Analytics generated with Speed Booster Pack by Optimocha. -->\n";
				echo "<script>\n";
				echo "(function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){(i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)})(window,document,'script','" . $ga_script . "','ga');\n";
				echo "ga('create', '" . $sbp_options[ 'sbp_ga_tracking_id' ] . "', 'auto');\n";

				//disable display features
				if( !empty( $sbp_options[ 'sbp_disable_display_features' ] ) && $sbp_options[ 'sbp_disable_display_features' ] == "1") {
					echo "ga('set', 'allowAdFeatures', false);\n";
				}

				//anonymize ip
				if( !empty( $sbp_options[ 'sbp_anonymize_ip' ] ) && $sbp_options[ 'sbp_anonymize_ip' ] == "1" ) {
					echo "ga('set', 'anonymizeIp', true);\n";
				}

				echo "ga('send', 'pageview');\n";

				//adjusted bounce rate
				if( !empty( $sbp_options[ 'sbp_bounce_rate' ] ) ) {
					echo 'setTimeout("ga(' . "'send','event','adjusted bounce rate','" . $sbp_options[ 'sbp_bounce_rate' ] . " seconds')" . '"' . "," . $sbp_options[ 'sbp_bounce_rate' ] * 1000 . ");\n";
				}

				echo "</script>\n";
				echo "<!-- End of Local Analytics. -->\n\n";
			}
		}

	}	// END class Speed_Booster_Pack_Core
}	// END if(!class_exists('Speed_Booster_Pack_Core'))
