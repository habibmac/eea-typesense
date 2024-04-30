<?php

namespace Galantis\Toolkit\Backend;

use Galantis\Toolkit\Helpers\Logger;
use Galantis\Toolkit\Main\TypesenseAPI;

class AdminAjaxHandler {

	public static ?AdminAjaxHandler $instance = null;
	private Logger $logger;

	public static function getInstance(): ?AdminAjaxHandler {
		return is_null( self::$instance ) ? self::$instance = new self() : self::$instance;
	}

	public function __construct() {
		$this->logger = new Logger();
		//log callbacks
		add_action( 'wp_ajax_gala_toolkit_get_log_files', array( $this, 'getLogFiles' ) );
		add_action( 'wp_ajax_gala_toolkit_view_log_file', array( $this, 'viewLogFile' ) );
		add_action( 'wp_ajax_gala_toolkit_get_site_info', array( $this, 'getSiteInfo' ) );
		add_action( 'wp_ajax_gala_toolkit_delete_all_log_files', array( $this, 'deleteLogFiles' ) );

		//addons
		add_action( 'wp_ajax_gala_toolkit_get_addons', array( $this, 'get_addons' ) );
	}

	public function get_addons() {
		wp_send_json( apply_filters( 'gala_toolkit_enabled_addons', array() ) );
	}

	/**
	 * @param $posted_data
	 *
	 * @return bool
	 */
	private function validateGetAccess( $posted_data ): bool {

		/*Bail Early*/
		if ( ! current_user_can( 'manage_options' ) ) {
			return false;
		}

		if ( empty( $posted_data['nonce'] ) ) {
			return false;
		}

		if ( ! wp_verify_nonce( $posted_data['nonce'], 'gala_toolkit_ValidateNonce' ) ) {
			return false;
		}

		return true;
	}

	public function getLogFiles(): void {
		$request_body = file_get_contents( 'php://input' );
		$posted_data  = json_decode( $request_body, true );

		if ( ! $this->validateGetAccess( $posted_data ) ) {
			wp_send_json( false );
		}


		$request_body = file_get_contents( 'php://input' );
		$posted_data  = json_decode( $request_body, true );
		$log_type     = is_array( $posted_data ) ? $posted_data['log_type'] : 'debug';

		$files = $this->logger->readAllErrorLogFiles( $log_type );
		wp_send_json( $files );
	}

	public function viewLogFile(): void {
		$request_body = file_get_contents( 'php://input' );
		$posted_data  = json_decode( $request_body, true );

		if ( ! $this->validateGetAccess( $posted_data ) ) {
			wp_send_json( false );
		}

		$request_body = file_get_contents( 'php://input' );
		$posted_data  = json_decode( $request_body, true );
		$filename     = is_array( $posted_data ) ? $posted_data['filename'] : '';
		$log_type     = is_array( $posted_data ) ? $posted_data['log_type'] : '';


		if ( ! isset( $filename ) || '' === $filename ) {
			wp_send_json( array() );
		}

		$logData = array();

		if ( 'error' === $log_type ) {
			$logData = array( 'logData' => $this->logger->readErrorFile( $filename ) );
		} elseif ( 'debug' === $log_type ) {
			$logData = array( 'logData' => $this->logger->readDebugFile( $filename ) );
		}
		wp_send_json( $logData );
	}

	public function getSiteInfo(): void {
		if ( ! class_exists( 'WP_Debug_Data' ) ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-debug-data.php';
		}

		$site_data = \WP_Debug_Data::debug_data();
		$site_data = \WP_Debug_Data::format( $site_data, 'value' );

		$search_config_settings = Admin::get_search_config_settings();
		$enabled_post_types     = $search_config_settings['enabled_post_types'];
		// pre_dump( $search_config_settings ); die;

		$schemas = "### schemas ### \r\n\r\n";
		foreach ( $enabled_post_types as $index_name ) {
			// Change to json to concatinate as string since WP_Debug_Data::format can't format schemas.
			$schema   = wp_json_encode( TypesenseAPI::getInstance()->getSchema( $index_name ) ) . "\r\n\r\n";
			$schemas .= '=======' . $index_name . "====== \r\n" . $schema;
		}

		$server_info  = "### Typesense server info ### \r\n\r\n";
		$server_info .= wp_json_encode( TypesenseAPI::getInstance()->getDebugInfo() ) . "\r\n\r\n";
		$server_info .= 'Health: ' . wp_json_encode( TypesenseAPI::getInstance()->getServerHealth() );

		$info = $site_data . $schemas . $server_info;

		wp_send_json( $info );
	}

	public function deleteLogFiles(): void {
		$request_body = file_get_contents( 'php://input' );
		$posted_data  = json_decode( $request_body, true );

		if ( ! $this->validateGetAccess( $posted_data ) ) {
			wp_send_json( false );
		}

		if ( empty( $posted_data['log_type'] ) ) {
			wp_send_json( false );
		}

		$this->logger->deleteAllFiles( $posted_data['log_type'] );
		wp_send_json( true );
	}
}
