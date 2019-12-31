<?php
/**
 * @package speed-booster-pack
 */

// Security control for vulnerability attempts
if( !defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}

// Database Tables and Options name Configrations
$option_names	= [ 'sbp_settings', 'sbp_news' ];
$table_names	= '';

if( !is_array( $option_names ) ) {
	$option_names = [ $option_names ];
}

if( !is_array( $table_names ) ) {
	$table_names = [ $table_names ];
}

foreach( $option_names as $option_name ) {

	if( empty( $option_name ) ) continue;

	if( is_multisite() ) {
		delete_site_option( $option_name );
	}
	else {
		delete_option( $option_name );
	}
}
