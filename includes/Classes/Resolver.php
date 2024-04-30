<?php

namespace Galantis\Toolkit\Classes;

use Exception;

trait Resolver {

    private array $manifest = array();

    /**
     * @action wp_enqueue_scripts 1
     */
    public function load(): void {
        $path = $this->viteManifest();

        if ( empty( $path ) || ! file_exists( $path ) ) {
            wp_die( __( 'Run <code>npm run build</code> in your application root!', 'fm' ) );
        }

        $this->manifest = json_decode( file_get_contents( $path ), true );
    }

	private function viteManifest() {
        $manifestPath = GALA_TOOLKIT_DIR . 'assets/manifest.json';

        if ( ! file_exists( $manifestPath ) ) {
            throw new Exception( 'Vite Manifest Not Found. Run : npm run dev or npm run prod' );
        }
        $manifestFile                       = fopen( $manifestPath, 'r' );

        if ( ! $manifestFile ) {
            throw new Exception( 'Vite Manifest Not Found. Run : npm run dev or npm run prod' );
        }
        
        return $manifestPath;
    }

    /**
     * @filter script_loader_tag 1 3
     */
    public function module( string $tag, string $handle, string $url ): string {
        if ( ( false !== strpos( $url, GALA_HMR_HOST ) ) || ( false !== strpos( $url, GALA_ASSETS_URI ) ) ) {
            $tag = str_replace( '<script ', '<script type="module" ', $tag );
        }

        return $tag;
    }

    private function resolve( string $path ): string {
        $url = '';

        if ( ! empty( $this->manifest[ "resources/{$path}" ] ) ) {
            $url = GALA_ASSETS_URI . "/{$this->manifest["resources/{$path}"]['file']}";
        }

        return apply_filters( 'fm/assets/resolver/url', $url, $path );
    }
}
