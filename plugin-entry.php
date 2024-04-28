<?php

/**
 * Plugin Name: Galantis - Typesense
 * Plugin URI: https://galanesia.com/
 * Description: A plugin for Galantis to manage Typesense search engine
 * Author: mc
 * Author URI: https://galanesia.com/
 * Version: 1.0.6
 * Text Domain: galantis-typesense
 */

define('GALANTIS_TYPESENSE_URL', plugin_dir_url(__FILE__));
define('GALANTIS_TYPESENSE_DIR', plugin_dir_path(__FILE__));

define('GALANTIS_TYPESENSE_VERSION', '1.0.5');

// This will automatically update, when you run dev or production
define('GALANTIS_TYPESENSE_DEVELOPMENT', 'yes');

class GalantisTypesense {
    public function boot()
    {
        $this->loadClasses();
        $this->registerShortCodes();
        $this->ActivatePlugin();
        $this->renderMenu();
        $this->disableUpdateNag();
        $this->loadTextDomain();
    }

    public function loadClasses()
    {
        require GALANTIS_TYPESENSE_DIR . 'includes/autoload.php';
    }

    public function renderMenu()
    {
        add_action('admin_menu', function () {
            if (!current_user_can('manage_options')) {
                return;
            }
            global $submenu;
            add_menu_page(
                'GalantisTypesense',
                'Galantis Typesense',
                'manage_options',
                'galantis-typesense.php',
                array($this, 'renderAdminPage'),
                'dashicons-editor-code',
                25
            );
            $submenu['galantis-typesense.php']['dashboard'] = array(
                'Dashboard',
                'manage_options',
                'admin.php?page=galantis-typesense.php#/',
            );
            $submenu['galantis-typesense.php']['contact'] = array(
                'Contact',
                'manage_options',
                'admin.php?page=galantis-typesense.php#/contact',
            );
        });
    }

    /**
     * Main admin Page where the Vue app will be rendered
     * For translatable string localization you may use like this
     * 
     *      add_filter('galantis_typesense/frontend_translatable_strings', function($translatable){
     *          $translatable['world'] = __('World', 'galantis-typesense');
     *          return $translatable;
     *      }, 10, 1);
     */
    public function renderAdminPage()
    {
        $loadAssets = new \Galantis\Typesense\Classes\LoadAssets();
        $loadAssets->admin();

        $translatable = apply_filters('galantis_typesense/frontend_translatable_strings', array(
            'hello' => __('Hello', 'galantis-typesense'),
        ));

        $galantis_typesense = apply_filters('galantis_typesense/admin_app_vars', array(
            'assets_url' => GALANTIS_TYPESENSE_URL . 'assets/',
            'ajaxurl' => admin_url('admin-ajax.php'),
            'i18n' => $translatable
        ));

        wp_localize_script('galantis-typesense-script-boot', 'galantisTypesenseAdmin', $galantis_typesense);

        echo '<div class="galantis_typesense-admin-page" id="galantis_typesense_app">
            <div class="main-menu text-white-200 bg-wheat-600 p-4">
                <router-link to="/">
                    Home
                </router-link> |
                <router-link to="/contact" >
                    Contacts
                </router-link>
            </div>
            <hr/>
            <router-view></router-view>
        </div>';
    }

    /*
    * NB: text-domain should match exact same as plugin directory name (Plugin Name)
    * WordPress plugin convention: if plugin name is "My Plugin", then text-domain should be "my-plugin"
    * 
    * For PHP you can use __() or _e() function to translate text like this __('My Text', 'galantis-typesense')
    * For Vue you can use $t('My Text') to translate text, You must have to localize "My Text" in PHP first
    * Check example in "renderAdminPage" function, how to localize text for Vue in i18n array
    */
    public function loadTextDomain()
    {
        load_plugin_textdomain('galantis-typesense', false, basename(dirname(__FILE__)) . '/languages');
    }


    /**
     * Disable update nag for the dashboard area
     */
    public function disableUpdateNag()
    {
        add_action('admin_init', function () {
            $disablePages = [
                'galantis-typesense.php',
            ];

            if (isset($_GET['page']) && in_array($_GET['page'], $disablePages)) {
                remove_all_actions('admin_notices');
            }
        }, 20);
    }


    /**
     * Activate plugin
     * Migrate DB tables if needed
     */
    public function ActivatePlugin()
    {
        register_activation_hook(__FILE__, function ($newWorkWide) {
            require_once(GALANTIS_TYPESENSE_DIR . 'includes/Classes/Activator.php');
            $activator = new \Galantis\Typesense\Classes\Activator();
            $activator->migrateDatabases($newWorkWide);
        });
    }


    /**
     * Register ShortCodes here
     */
    public function registerShortCodes()
    {
        // Use add_shortcode('shortcode_name', 'function_name') to register shortcode
    }
}

(new GalantisTypesense())->boot();



