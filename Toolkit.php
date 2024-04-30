<?php

namespace Galantis;

class Toolkit {

    private string $admin_page_url = 'galantis-toolkit';

    public function boot() {
        $this->load_classes();
        $this->register_shortcode();
        $this->activate_plugin();
        $this->render_menu();
        $this->load_text_domain();
    }

    public function load_classes() {
        require GALA_TOOLKIT_DIR . 'includes/autoload.php';
    }

    public function render_menu() {
        add_action(
            'admin_menu',
            function () {
				if ( ! current_user_can( 'manage_options' ) ) {
					return;
				}
				add_menu_page(
                    'Galantis Toolkit',
                    'Galantis Toolkit',
                    'manage_options',
                    $this->admin_page_url,
                    array( $this, 'render_admin_page' ),
                    'dashicons-editor-code',
                    25
				);

				add_submenu_page(
                    'admin.php?page=galantis-toolkit.php#/logs',
                    'Logs',
                    'Logs',
                    'manage_options',
                    $this->admin_page_url,
                );
                add_submenu_page(
                    'admin.php?page=galantis-toolkit.php#/server',
                    'Server',
                    'Server',
                    'manage_options',
                    $this->admin_page_url,
                );
                add_submenu_page(
                    'admin.php?page=galantis-toolkit.php#/',
                    'Collections',
                    'Collections',
                    'manage_options',
                    $this->admin_page_url,
                );
			}
        );
    }

    /**
     * Main admin Page where the Vue app will be rendered
     * For translatable string localization you may use like this
     *
     *      add_filter('galantis_toolkit/frontend_translatable_strings', function($translatable){
     *          $translatable['world'] = __('World', 'galantis-toolkit');
     *          return $translatable;
     *      }, 10, 1);
     */
    public function render_admin_page() {
        $load_assets = new \Galantis\Toolkit\Classes\LoadAssets();
        $load_assets->admin();

        $galantis_toolkit = apply_filters(
            'galantis_toolkit__admin_app_vars',
            array(
				'assets_url' => GALA_TOOLKIT_URL . 'assets/',
				'ajaxurl'    => admin_url( 'admin-ajax.php' ),
            )
        );

        wp_localize_script( 'galantis-toolkit-script-boot', 'Galantis_ToolkitAdmin', $galantis_toolkit );
    }

    /*
    * NB: text-domain should match exact same as plugin directory name (Plugin Name)
    * WordPress plugin convention: if plugin name is "My Plugin", then text-domain should be "my-plugin"
    *
    * For PHP you can use __() or _e() function to translate text like this __('My Text', 'galantis-toolkit')
    * For Vue you can use $t('My Text') to translate text, You must have to localize "My Text" in PHP first
    * Check example in "render_admin_page" function, how to localize text for Vue in i18n array
    */
    public function load_text_domain() {
        load_plugin_textdomain( 'galantis-toolkit', false, basename( __DIR__ ) . '/languages' );
    }

    /**
     * Activate plugin
     * Migrate DB tables if needed
     */
    public function activate_plugin() {
        register_activation_hook(
            __FILE__,
            function ( $new_work_widc ) {
				require_once GALA_TOOLKIT_DIR . 'includes/Classes/Activator.php';
				$activator = new \Galantis\Toolkit\Classes\Activator();
				$activator->migrateDatabases( $new_work_widc );
			}
        );
    }

    /**
     * Register ShortCodes here
     */
    public function register_shortcode() {
        // Use add_shortcode('shortcode_name', 'function_name') to register shortcode
    }
}

( new Toolkit() )->boot();
