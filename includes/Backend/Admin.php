<?php

namespace Galantis\Toolkit\Backend;

use Galantis\Toolkit\Helpers\Logger;
use Galantis\Toolkit\Main\TypesenseAPI;
use WPStrap\Vite\Assets;
use WPStrap\Vite\DevServer;

class Admin {
	public static ?Admin $instance            = null;
	public string $menu_page                  = '';
	private string $admin_page_url            = 'galantis-toolkit';
	public static string $general_options_key = 'gala_toolkit_admin_settings';
	public static string $search_config_key   = 'gala_toolkit_search_config_settings';

	public static array $default_settings
		= array(
			'protocol'       => 'https://',
			'node'           => '',
			'admin_api_key'  => '',
			'search_api_key' => '',
			'port'           => '443',
			'debug_log'      => false,
			'error_log'      => true,
		);

	public static array $search_config_settings
		= array(
			'enabled_post_types'   => array(
				'event_espresso',
			),

			'available_post_types' => array(
				'event_espresso' => array(
					'label' => 'EE',
					'value' => 'espresso_events',
					'type'  => 'ee',
				),
			),
			//to be used on frontend to show label if multiple collections are searched at once
			'config'               => array(
				'post_type' => array(
					'post' => array(
						'label'           => 'Post',
						'max_suggestions' => 3,
					),
				),
				//taxonomy has not been implemented yet but adding now for future
				'taxonomy'  => array(
					'category' => 'Categories',
				),
			),
			//due to backward compatibility autocomplete is default option
		);
	/**
	 * @var false|string
	 */
	private $settings_page_slug;
	/**
	 * @var false|string
	 */
	private $log_page_slug;

	/**
	 * @return Admin|null
	 */
	public static function getInstance(): ?Admin {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function __construct() {

		Assets::register(
            array(
				'dir'     => GALA_TOOLKIT_DIR,
				'url'     => plugins_url( \basename( __DIR__ ) ),
				'version' => GALA_TOOLKIT_VERSION,
				'deps'    => array(
					'scripts' => array(),
					'styles'  => array(),
				),
            )
        );

		Assets::devServer()->start();

		/*load dependencies if required*/
		add_action( 'admin_menu', array( $this, 'admin_menu_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'load_textdomain' ), 100 );

		//Plugin Settings Link
		add_filter( 'plugin_action_links_search-with-typesense/galantis-typesense.php', array( $this, 'settings_link' ) );

		add_action( 'wp_ajax_getGalaToolkitAdminSettings', array( $this, 'getSettings' ) );
		add_action( 'wp_ajax_getGalaToolkitSearchConfig', array( $this, 'getSearchConfig' ) );

		add_action( 'wp_ajax_saveGalaToolkitAdminSettings', array( $this, 'saveGeneralSettings' ) );
		add_action( 'wp_ajax_saveGalaToolkitSearchConfigSettings', array( $this, 'saveSearchConfigSettings' ) );

		//Get Schema Details
		add_action( 'wp_ajax_getGalaToolkitTypesenseSchema', array( $this, 'getSchemaDetails' ) );

		//Drop Collection
		add_action( 'wp_ajax_GalaToolkitDropCollection', array( $this, 'handleDropCollection' ) );

		//Import function
		add_action( 'wp_ajax_GalaToolkitBulkImport', array( $this, 'GalaToolkitBulkImport' ) );

		//Delete Log File
		add_action( 'wp_ajax_GalaToolkitDeleteFile', array( $this, 'GalaToolkitDeleteFile' ) );

		//activate ajax handler
		AdminAjaxHandler::getInstance();
	}

	public function getSchemaDetails() {
		$posted_data = array( 'nonce' => filter_input( INPUT_GET, 'nonce' ) );
		if ( ! $this->validateGetAccess( $posted_data ) ) {
			return false;
		}
		$collection_name                = filter_input( INPUT_GET, 'collection_name' );
		$maybe_prefixed_collection_name = TypesenseAPI::getInstance()->getCollectionNameFromSchema( $collection_name );
		$schemaDetails                  = TypesenseAPI::getInstance()->getCollectionInfo( $maybe_prefixed_collection_name );
		wp_send_json( $schemaDetails );
	}

	/**
	 * @param $links
	 *
	 * @return array
	 */
	public function settings_link( $links ): array {
		// Build and escape the URL.
		$url = esc_url(
            add_query_arg(
                array(
					'page' => 'galantis-toolkit',
					'tab'  => 'general',
                ),
                get_admin_url() . 'admin.php'
            )
        );
		// Create the link.
		$settings_link = "<a href='$url'>" . __( 'Settings' ) . '</a>';

		//documentation link
		$documentation_link = '<a href="https://docs.wptypesense.com/" target="_blank" rel="noopener nofollow">Documentation</a>';

		// Adds the link to the end of the array.
		return array_merge(
            array(
				'settings'      => $settings_link,
				'documentation' => $documentation_link,
            ),
            $links
        );
	}

	/**
	 * @return array
	 */
	public static function get_default_settings(): array {
		return wp_parse_args( get_option( self::$general_options_key ), self::$default_settings );
	}

	/**
	 * @return array
	 */
	public static function get_search_config_settings(): array {
		$search_config_settings                         = wp_parse_args( get_option( self::$search_config_key ), self::$search_config_settings );
		$available_index_types                          = apply_filters_deprecated( 'gala_toolkit_available_post_types', array( $search_config_settings['available_post_types'] ), '1.3.0', 'gala_toolkit_available_index_types' );
		$search_config_settings['available_post_types'] = apply_filters( 'gala_toolkit_available_index_types', $available_index_types );
		//this code is confusing i know but it was the simplest solution
		//what i'm doing is the available post type is first keyed via post slug and then when someone uses the filter they can add their own
		//now we loop through the keyed [post_slug] => ['label'=>post_label, 'value'=>post_value] format and make it ready for consumption by react admin settings
		$formatted_available_post_types = array();
		$available_post_type_slugs      = array();
		foreach ( $search_config_settings['available_post_types'] as $key => $value ) {
			$formatted_available_post_types[ $key ] = $value;
			//use to remove unavailable enabled post types
			$available_post_type_slugs[] = $key;
		}
		$search_config_settings['available_post_types'] = $formatted_available_post_types;
		//use to remove unavailable enabled post types
		$search_config_settings['enabled_post_types'] = array_values( array_intersect( $search_config_settings['enabled_post_types'], $available_post_type_slugs ) );


		return $search_config_settings;
	}

	/**
	 * Add Admin Menu for Plugin
	 */
	public function admin_menu_page(): void {
		$this->menu_page = add_menu_page(
			__( 'Galantis Toolkit', 'gala-typesense' ),
			__( 'Toolkit' ),
			'manage_options',
			$this->admin_page_url,
			array( $this, 'generate_admin_page' ),
			'dashicons-hammer',
		);
	}

	//to be retrieved in ajax call
	public function getSettings() {
		$posted_data = array( 'nonce' => filter_input( INPUT_GET, 'nonce' ) );

		if ( ! $this->validateGetAccess( $posted_data ) ) {
			wp_send_json( false );
		}

		wp_send_json( self::get_default_settings() );
	}

	//to be retrieved in ajax call
	public function getSearchConfig() {
		$posted_data = array( 'nonce' => filter_input( INPUT_GET, 'nonce' ) );

		if ( ! $this->validateGetAccess( $posted_data ) ) {
			wp_send_json( false );
		}

		wp_send_json( self::get_search_config_settings() );
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

	/**
	 * @param $settings
	 *
	 * @return array
	 */
	private function sanitizeGeneralSettings( $settings ): array {

		$sanitizeSettings = self::$default_settings;

		$sanitizeSettings['protocol']       = ( 'https://' === $settings['protocol'] || 'http://' === $settings['protocol'] ) ? $settings['protocol'] : 'https://';
		$sanitizeSettings['admin_api_key']  = sanitize_text_field( $settings['admin_api_key'] );
		$sanitizeSettings['search_api_key'] = sanitize_text_field( $settings['search_api_key'] );
		$sanitizeSettings['port']           = is_numeric( $settings['port'] ) ? $settings['port'] : 443;
		$sanitizeSettings['debug_log']      = is_bool( $settings['debug_log'] ) && $settings['debug_log'];
		$sanitizeSettings['error_log']      = is_bool( $settings['error_log'] ) && $settings['error_log'];
		$sanitizeSettings['node']           = sanitize_text_field( $settings['node'] );

		return $sanitizeSettings;
	}

	/**
	 * @param $newSettings
	 *
	 * @return array
	 */
	private function sanitizeSearchConfigSettings( $newSettings ): array {
		$sanitizeSettings = self::$search_config_settings;

		//either instant_search or autocomplete - default will be instant_search
		$sanitizeSettings['hijack_wp_search__type']        = $newSettings['hijack_wp_search__type'] ?? $sanitizeSettings['hijack_wp_search__type'];
		$sanitizeSettings['autocomplete_placeholder_text'] = sanitize_text_field( $newSettings['autocomplete_placeholder_text'] ?? $sanitizeSettings['autocomplete_placeholder_text'] );
		$sanitizeSettings['autocomplete_input_delay']      = sanitize_text_field( $newSettings['autocomplete_input_delay'] ?? $sanitizeSettings['autocomplete_input_delay'] );
		$sanitizeSettings['autocomplete_submit_action']    = sanitize_text_field( $newSettings['autocomplete_submit_action'] ?? $sanitizeSettings['autocomplete_submit_action'] );

		$sanitizeSettings['enabled_post_types'] = is_array( $newSettings['enabled_post_types'] ) ? $this->sanitizeArrayAsTextField( $newSettings['enabled_post_types'] ) : $sanitizeSettings['enabled_post_types'];

		unset( $sanitizeSettings['available_post_types'] );

		if ( isset( $newSettings['config']['post_type'] ) && is_array( $newSettings['config']['post_type'] ) ) {
			$newPostTypeConfig = array();
			foreach ( $newSettings['config']['post_type'] as $post_slug => $post_configuration ) {
				$newPostTypeConfig[ sanitize_text_field( $post_slug ) ]['label']           = sanitize_text_field( $post_configuration['label'] );
				$newPostTypeConfig[ sanitize_text_field( $post_slug ) ]['max_suggestions'] = sanitize_text_field( $post_configuration['max_suggestions'] );
			}
			$sanitizeSettings['config']['post_type'] = $newPostTypeConfig;
		}

		return $sanitizeSettings;
	}

	/**
	 * @param $data
	 *
	 * @return array
	 */
	public function sanitizeArrayAsTextField( $data ): array {
		return array_map(
            static function ( $item ) {
				return sanitize_text_field( $item );
			},
            $data
        );
	}

	/**
	 * This is for general settings
	 */
	public function saveGeneralSettings() {
		$request_body = file_get_contents( 'php://input' );
		$posted_data  = json_decode( $request_body, true );
		if ( ! $this->validateGetAccess( $posted_data ) ) {
			return false;
		}

		$updatedSettings = $this->sanitizeGeneralSettings( $posted_data['settings'] );

		update_option( self::$general_options_key, $updatedSettings );
		$server_health = TypesenseAPI::getInstance()->getDebugInfo();
		$response      = array(
			'settings' => $updatedSettings,
			'notice'   => array(
				'status'  => 'success',
				'message' => 'Settings Saved',
			),
		);
		if ( is_wp_error( $server_health ) ) {
			$response['notice']['status']  = 'error';
			$response['notice']['message'] = 'The credentials could not be verified - please check your settings';
		}
		wp_send_json( $response );
	}

	/**
	 * @return false|void
	 */
	public function saveSearchConfigSettings() {
		$request_body = file_get_contents( 'php://input' );
		$posted_data  = json_decode( $request_body, true );
		if ( ! $this->validateGetAccess( $posted_data ) ) {
			return false;
		}

		$updatedSettings = $this->sanitizeSearchConfigSettings( $posted_data['settings'] );

		update_option( self::$search_config_key, $updatedSettings );
		wp_send_json(
            array(
				'settings' => $updatedSettings,
				'notice'   => array(
					'status'  => 'success',
					'message' => 'Settings Saved',
				),
            )
        );
	}

	public function GalaToolkitDeleteFile() {
		$request_body = file_get_contents( 'php://input' );
		$posted_data  = json_decode( $request_body, true );
		if ( ! $this->validateGetAccess( $posted_data ) ) {
			return false;
		}

		if ( ! isset( $posted_data['filename'] ) || ! isset( $posted_data['log_type'] ) ) {
			wp_send_json(
                array(
					'notice' => array(
						'status'  => 'error',
						'message' => 'Bad Request',
					),
                )
            );
		}

		$logger  = new Logger();
		$deleted = $logger->deleteFile( $posted_data['log_type'], $posted_data['filename'] );

		if ( $deleted ) {
			wp_send_json(
                array(
					'notice' => array(
						'status'  => 'success',
						'message' => 'File Deleted',
					),
                )
            );
		} else {
			wp_send_json(
                array(
					'notice' => array(
						'status'  => 'error',
						'message' => 'Failure',
					),
                )
            );
		}
	}

	public function handleDropCollection() {
		$request_body = file_get_contents( 'php://input' );
		$posted_data  = json_decode( $request_body, true );
		if ( ! $this->validateGetAccess( $posted_data ) ) {
			return false;
		}

		$result   = TypesenseAPI::getInstance()->dropCollection( $posted_data['collectionName'] );
		$response = '';
		if ( is_wp_error( $result ) ) {
			$response = array(
				'notice' => array(
					'status'  => 'error',
					'message' => $result->get_error_message(),
				),
			);
		} else {
			$response = array(
				'notice' => array(
					'status' => 'success',
				),
			);
		}

		wp_send_json( $response );
	}

	public function bulkImportPosts( $posted_data, $send_json = true ) {
		$posts_per_page = apply_filters( 'gala_toolkit_bulk_posts_per_page', 10 );
		$args           = array(
			'post_type'      => $posted_data['post_type'],
			'posts_per_page' => $posts_per_page,
			'post_status'    => apply_filters( 'gala_toolkit_post_status', 'publish' ),
		);
		if ( isset( $posted_data['offset'] ) && 0 !== $posted_data['offset'] ) {
			$args['offset'] = $posted_data['offset'];
		}
		$posts = new \WP_Query( apply_filters( 'gala_toolkit_bulk_index_query_args', $args ) );
		if ( $posts->have_posts() ) :
			$documents   = array();
			$total_posts = $posts->found_posts;
			foreach ( $posts->posts as $post ) {
				if ( apply_filters( 'gala_toolkit_bulk_import_skip_post', false, $post ) ) {
					//if you want to delete on bulk import you can
					do_action( 'gala_toolkit_bulk_import_on_post_skipped', $post );
					continue;
				}
				$documents[] = TypesenseAPI::getInstance()->formatDocumentForEntry( $post, $post->ID, $post->post_type );
			}

			$result = (object) TypesenseAPI::getInstance()->bulkUpsertDocuments( $posted_data['post_type'], $documents );

			//var_dump(is_wp_error($result)); die;
			if ( is_wp_error( $result ) ) {
				$error_string = $result->get_error_code();
				if ( is_wp_error( $result ) &&  404 === $error_string ) { // phpcs:ignore
					$schema             = TypesenseAPI::getInstance()->getSchema( $posted_data['post_type'] );
					$schemaMaybeCreated = TypesenseAPI::getInstance()->createCollection( $schema );
					if ( is_object( $schemaMaybeCreated ) && TypesenseAPI::getInstance()->getCollectionNameFromSchema( $post->post_type ) === $schemaMaybeCreated->name ) {
						TypesenseAPI::getInstance()->bulkUpsertDocuments( $posted_data['post_type'], $documents );
						$response = array(
							'status'  => 'success',
							'notice'  => array(
								'status'  => 'success',
								'message' => 'All entries for "' . $posted_data['post_type'] . '" have been successfully Imported',
							),
							'addInfo' => array(
								'total_posts'    => $total_posts,
								'offset'         => $posted_data['offset'] + $posts_per_page,
								'posts_per_page' => $posts_per_page,
							),
						);
						if ( $send_json ) {
							wp_send_json( $response );
						} else {
							return $response;
						}
					} else {
						wp_send_json(
                            array(
								'notice' => array(
									'status'  => 'error',
									'message' => $posted_data['post_type'] . ' were not imported please check error log',
								),
                            )
                        );
					}
				} else {
					$log = new Logger();
					$log->logError( $result );
				}
			} else {

				$response = array(
					'status'  => 'success',
					'notice'  => array(
						'status'  => 'success',
						'message' => 'All entries for "' . $posted_data['post_type'] . '" have been successfully Imported',
					),
					'addInfo' => array(
						'total_posts'    => $total_posts,
						'offset'         => $posted_data['offset'] + $posts_per_page,
						'posts_per_page' => $posts_per_page,
					),
				);
				if ( true === $send_json ) {
					wp_send_json( $response );
				} else {
					return $response;
				}
			}
		endif;
	}

	public function bulkImportTaxonomy( $posted_data ) {
		$taxonomy = trim( $posted_data['post_type'] );

		$terms_per_page = apply_filters( 'gala_toolkit_bulk_posts_per_page', 40 );

		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'number'     => $terms_per_page,
		);

		if ( isset( $posted_data['offset'] ) && 0 !== $posted_data['offset'] ) {
			$args['offset'] = $posted_data['offset'];
		}
		$terms = new \WP_Term_Query( apply_filters( 'gala_toolkit_bulk_index_query_args', $args ) );

		$documents = array();

		$total_terms = wp_count_terms( $taxonomy );

		foreach ( $terms->get_terms() as $term ) {
			if ( apply_filters( 'gala_toolkit_bulk_import_skip_post', false, $term ) ) {
				//if you want to delete on bulk import you can
				do_action( 'gala_toolkit_bulk_import_on_term_skipped', $term );
				continue;
			}
			$documents[] = TypesenseAPI::getInstance()->formatDocumentForEntry( $term, $term->term_id, $taxonomy );
		}


		$result = (object) TypesenseAPI::getInstance()->bulkUpsertDocuments( $taxonomy, $documents );

		if ( is_wp_error( $result ) ) {
			if ( is_wp_error( $result ) && 404 === $result->get_error_code() ) {
				$schema             = TypesenseAPI::getInstance()->getSchema( $taxonomy );
				$schemaMaybeCreated = TypesenseAPI::getInstance()->createCollection( $schema );
				if ( is_object( $schemaMaybeCreated ) && TypesenseAPI::getInstance()->getCollectionNameFromSchema( $taxonomy ) === $schemaMaybeCreated->name ) {
					TypesenseAPI::getInstance()->bulkUpsertDocuments( $taxonomy, $documents );
					wp_send_json(
                        array(
							'status'  => 'success',
							'notice'  => array(
								'status'  => 'success',
								'message' => 'All entries for "' . $taxonomy . '" have been successfully Imported',
							),
							'addInfo' => array(
								'total_posts'    => $total_terms,
								'offset'         => $posted_data['offset'] + $terms_per_page,
								'posts_per_page' => $terms_per_page,
							),
                        )
                    );
				} else {
					wp_send_json(
                        array(
							'notice' => array(
								'status'  => 'error',
								'message' => $taxonomy . ' were not imported please check error log',
							),
                        )
                    );
				}
			} else {
				$log = new Logger();
				$log->logError( $result );
			}
		} else {
			wp_send_json(
                array(
					'status'  => 'success',
					'notice'  => array(
						'status'  => 'success',
						'message' => 'All entries for "' . $taxonomy . '" have been successfully Imported',
					),
					'addInfo' => array(
						'total_posts'    => $total_terms,
						'offset'         => $posted_data['offset'] + $terms_per_page,
						'posts_per_page' => $terms_per_page,
					),
                )
			);
		}
	}

	/**
	 * @throws \JsonException
	 */
	public function GalaToolkitBulkImport() {
		// Check if node is not expired and CURL on TS server is working and return early if it doesn't work.
		$server_info = TypesenseAPI::getInstance()->getServerHealth();
		if ( property_exists( $server_info, 'errors' ) ) {
			$errors = $server_info->errors;

			if ( isset( $errors['http_request_failed'] ) ) {
				wp_send_json(
                    array(
						'notice' => array(
							'status'  => 'error',
							'message' => wp_sprintf( '%s Check %shere%s for more details.', $errors['http_request_failed'][0], '<a href="https://galanesia.com/" target="_blank">', '</a>' ),
						),
                    )
                );

				return false;
			}
		}

		$request_body = file_get_contents( 'php://input' );
		$posted_data  = json_decode( $request_body, true );
		if ( ! $this->validateGetAccess( $posted_data ) ) {
			return false;
		}

		//Old
		$updatedSettings = $this->sanitizeSearchConfigSettings( $posted_data['settings'] );
		update_option( self::$search_config_key, $updatedSettings );
		do_action( 'gala_toolkit_before_bulk_index' );

		// if ( 'post_type' == $posted_data['index_type'] ) :
		//  $this->bulkImportPosts( $posted_data );

		// elseif ( 'taxonomy' == $posted_data['index_type'] ) :
		//  $this->bulkImportTaxonomy( $posted_data );
		// endif;

		if ( 'post_type' === $posted_data['index_type'] ) :
			$this->bulkImportPosts( $posted_data );

		elseif ( 'taxonomy' === $posted_data['index_type'] ) :
			$this->bulkImportTaxonomy( $posted_data );
		endif;

		do_action( 'gala_toolkit_after_bulk_index' );

		wp_send_json(
            array(
				'notice' => array(
					'status'  => 'error',
					'message' => 'Something Has gone wrong while bulk importing',
				),
            )
        );
	}

	/**
	 * @param $hook_suffix
	 */
	public function load_scripts( $hook_suffix ): void {
		// $script = array();
		// if ( file_exists( GALA_TOOLKIT_DIR . '/assets/admin/index.asset.php' ) ) {
		//  $script = include_once GALA_TOOLKIT_DIR . '/assets/admin/index.asset.php';
		// }
		// $dependencies = $script['dependencies'];

		// wp_register_script( 'gala-toolkit-admin-script', GALA_TOOLKIT_URL . 'assets/admin/index.js', $dependencies, $script['version'], true );
		// wp_register_style( 'gala-toolkit-admin-style', GALA_TOOLKIT_URL . 'assets/admin/style-index.css', array( 'wp-components' ), GALA_TOOLKIT_VERSION );
		// wp_localize_script(
        //     'gala-toolkit-admin-script',
        //     'galantisToolkitAdmin',
        //     array(
		//      'nonce'                         => wp_create_nonce( 'gala_toolkit_ValidateNonce' ),
		//      'assets_url'                    => GALA_TOOLKIT_URL . '/assets',
		//      'instant_search_customizer_url' => admin_url( '/customize.php?autofocus[section]=typesense_popup' ),
		//  )
        // );
		// if ( $hook_suffix === $this->menu_page || $hook_suffix === $this->settings_page_slug || $hook_suffix === $this->log_page_slug ) {
		//  wp_enqueue_script( 'gala-toolkit-admin-script' );
		//  wp_enqueue_style( 'gala-toolkit-admin-style' );
		// }

		// Assets::enqueueStyle( 'gala-toolkit-admin-style', 'main' );

		// // Which also comes with some handy chained methods
		// Assets::enqueueScript( 'gala-toolkit-admin-script', 'main', array( 'another-dep' ) )
		// 	->useAsync()
		// 	->useAttribute( 'key', 'value' )
		// 	->localize( 'object_name', array( 'data' => 'data' ) )
		// 	->appendInline( '<script>console.log("hello");</script>' );
	}

	public function load_textdomain( $hook_suffix ) {
		if ( $hook_suffix === $this->menu_page || $hook_suffix === $this->settings_page_slug ) {
			wp_set_script_translations( 'gala-toolkit-admin-script', 'gala-toolkit' );
		}
	}

	public function generate_admin_page() {
		require_once GALA_TOOLKIT_DIR . '/includes/views/AdminArea/index.php';
	}
}
