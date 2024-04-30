<?php

namespace Galantis\Toolkit;

use Galantis\Toolkit\Backend\Admin;
use Galantis\Toolkit\Main\EventListener;
use Galantis\Toolkit\WPCLI\WPCLI;

final class Bootstrap {
	const MINIMUM_PHP_VERSION           = '7.4';
	public static ?Bootstrap $_instance = null; //phpcs:ignore 

	/**
	 * @return Bootstrap|null
	 */
	public static function getInstance(): ?Bootstrap {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}

		return self::$_instance;
	}

	/**
	 * pluginName constructor.
	 */
	public function __construct() {

		if ( version_compare( PHP_VERSION, self::MINIMUM_PHP_VERSION, '<' ) ) {
			add_action( 'admin_notices', array( $this, 'admin_notice_minimum_php_version' ) );

			return;
		}
		$this->autoload();

		add_action( 'plugins_loaded', array( $this, 'initPlugin' ) );
		register_activation_hook( GALA_TOOLKIT_DIR, array( $this, 'plugin_activated' ) );
		register_deactivation_hook( GALA_TOOLKIT_DIR, array( $this, 'plugin_deactivated' ) );
		add_action( 'in_plugin_update_message-' . GALA_TOOLKIT_DIR, array( $this, 'plugin_update_message' ) );
	}

	public function plugin_activated() {
		//other plugins can get this option and check if plugin is activated
		update_option( 'galantis_toolkit_plugin_activate', 'activated' );
	}

	public function plugin_deactivated() {
		delete_option( 'galantis_toolkit_plugin_activate' );
	}

	public function admin_notice_minimum_php_version() {

		if ( isset( $_GET['activate'] ) ) {
			unset( $_GET['activate'] );
		}
		$message = sprintf(
		/* translators: 1: Plugin name 2: PHP 3: Required PHP version */
			esc_html__( '"%1$s" requires "%2$s" version %3$s or greater.', 'gala-toolkit' ),
			'<strong>' . esc_html__( 'Galantis Toolkit', 'gala-toolkit' ) . '</strong>',
			'<strong>' . esc_html__( 'PHP', 'gala-toolkit' ) . '</strong>',
			self::MINIMUM_PHP_VERSION
		);

		printf( '<div class="notice notice-warning is-dismissible"><p>%1$s</p></div>', esc_html( $message ) );
	}

	/**
	 * Autoload - PSR 4 Compliance
	 */
	public function autoload() {
		require_once GALA_TOOLKIT_DIR . '/vendor/autoload.php';
	}

	public function initPlugin() {
		Admin::getInstance();
		EventListener::getInstance();
		WPCLI::getInstance();

		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	public function load_textdomain() {
		load_plugin_textdomain( 'gala-toolkit', false, dirname( plugin_basename( GALA_TOOLKIT_DIR ) ) . '/languages' );
	}

	public function plugin_update_message( $plugin_data ) {
		$this->version_update_warning( GALA_TOOLKIT_VERSION, $plugin_data['new_version'] );
	}

	/**
	 * @param $plugin
	 *
	 * @return bool
	 */
	public function is_plugin_active( $plugin ): bool {
		return in_array( $plugin, (array) get_option( 'active_plugins', array() ), true );
	}

	public function version_update_warning( $current_version, $new_version ) {
		$current_version_minor_part = explode( '.', $current_version )[1];
		$new_version_minor_part     = explode( '.', $new_version )[1];
		if ( $current_version_minor_part === $new_version_minor_part ) {
			return;
		}
		?>
        <style>
            .cmswt-MajorUpdateNotice {
                display: flex;
                max-width: 1000px;
                margin: 0.5em 0;
            }

            .cmswt-MajorUpdateMessage {
                margin-left: 1rem;
            }

            .cmswt-MajorUpdateMessage-desc {
                margin-top: .5rem;
            }

            .cmswt-MajorUpdateNotice + p {
                display: none;
            }
        </style>
        <hr>
        <div class="cmswt-MajorUpdateNotice">
            <span class="dashicons dashicons-info" style="color: #f56e28;"></span>
            <div class="cmswt-MajorUpdateMessage">
                <strong class="cmswt-MajorUpdateMessage-title">
					<?php esc_html_e( 'Heads up, Please backup before upgrade!', 'gala-toolkit' ); ?>
                </strong>
                <div class="cmswt-MajorUpdateMessage-desc">
					<?php
					esc_html_e( 'The latest update includes some substantial changes across different areas of the plugin. We highly recommend you backup your site before upgrading, and make sure you first update in a staging environment', 'gala-toolkit' );
					?>
                </div>
            </div>
        </div>
		<?php
	}
}

Bootstrap::getInstance();
