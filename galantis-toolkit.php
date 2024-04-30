<?php

/**
 * Plugin Name: Galantis - Toolkit
 * Description: Turbocharges Galantis
 * Plugin URI: https://galanesia.com/
 * Author: mc
 * Author URI: https://galanesia.com/
 * Version: 1.0.6
 * Text Domain: galantis-toolkit
 */

define( 'GALA_TOOLKIT_VERSION', '1.0.6' );

define( 'GALA_TOOLKIT_URL', plugin_dir_url( __FILE__ ) );
define( 'GALA_TOOLKIT_DIR', plugin_dir_path( __FILE__ ) );

define( 'GALA_ASSETS_PATH', GALA_TOOLKIT_DIR . '/build' );
define( 'GALA_ASSETS_URI', GALA_TOOLKIT_URL . '/build' );
define( 'GALA_RESOURCES_PATH', GALA_TOOLKIT_DIR . '/src/assets' );
define( 'GALA_RESOURCES_URI', GALA_TOOLKIT_URL . '/src/assets' );

define( 'GALA_TOOLKIT_DEVELOPMENT', 'yes' );
define( 'GALA_HMR_HOST', 'localhost:3000' );

require_once GALA_TOOLKIT_DIR . '/vendor/autoload.php';
require_once GALA_TOOLKIT_DIR . '/includes/Bootstrap.php';
