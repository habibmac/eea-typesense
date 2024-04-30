<?php
// if uninstall.php is not called by WordPress, die
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	die;
}
$option_keys = array(
	'galantis_toolkit_plugin_activate',
);

foreach ( $option_keys as $option_key ) {
	delete_option( 'galantis_toolkit_plugin_activate' );
	delete_option( 'galantis_toolkit_admin_settings' );
	delete_option( 'galantis_toolkit_customizer_instant_search' );
}
