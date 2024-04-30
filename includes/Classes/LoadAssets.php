<?php

namespace Galantis\Toolkit\Classes;

use WPStrap\Vite\Assets;
use Galantis\Toolkit\Classes\Vite;

class LoadAssets {

    public function __construct() {
        add_action( 'admin_enqueue_scripts', array( $this, 'admin' ) );
    }

    public function admin() {
        // Vite::enqueueScript( 'galantis-toolkit-script-boot', 'admin/start.js', array( 'jquery' ), GALA_TOOLKIT_VERSION, true );

        add_action(
            'wp_enqueue_scripts',
            function () {
                // Enqueue a script
				Assets::enqueueStyle( 'galantis-toolkit-css', 'main' );

				// Which also comes with some handy chained methods
				Assets::enqueueScript( 'galantis-toolkit-js', 'main', array( 'another-dep' ) )
				->useAsync()
				->useAttribute( 'key', 'value' )
				->localize( 'galantis-toolkit-script-boot', array( 'data' => 'data' ) )
                ->appendInline(
                    '<div class="galantis_toolkit-admin-page" id="galantis_toolkit_app">
                        <ul class="galantis-top-menu p-4 flex space-x-2 divide-x divide-slate-400 ">
                            <li>
                                <router-link to="/">
                                    Collections
                                </router-link>
                            </li>
                            <li>
                                <router-link to="/server" >
                                    Server
                                </router-link>
                            </li>
                            <li>
                                <router-link to="/logs" >
                                    Logs
                                </router-link>
                            </li>
                        </ul>
                        <hr/>
                        <router-view></router-view>
                    </div>'
                );
			}
        );
    }
}
