<?php
/**
 * Plugin Name: FSF Product Grid
 * Description: Embeds a filterable grid of member-recommended products.
 * Version:     1.0.0
 * Author:      Rhumbline AI
 * License:     GPL v2 or later
 * Text Domain: fsf-product-grid
 */

defined( 'ABSPATH' ) || exit;

class FSF_Product_Grid {

    public function __construct() {
        add_shortcode( 'fsf_product_grid', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
    }

    public function enqueue_assets() {
        global $post;
        if ( ! $post || ! has_shortcode( $post->post_content, 'fsf_product_grid' ) ) {
            return;
        }

        $app_url  = plugin_dir_url( __FILE__ ) . 'app/';
        $app_path = plugin_dir_path( __FILE__ ) . 'app/';
        $css_ver  = file_exists( $app_path . 'product-grid.css' ) ? filemtime( $app_path . 'product-grid.css' ) : '1.0.0';
        $js_ver   = file_exists( $app_path . 'product-grid.js' )  ? filemtime( $app_path . 'product-grid.js' )  : '1.0.0';

        wp_enqueue_style(
            'fsf-product-grid',
            $app_url . 'product-grid.css',
            array(),
            $css_ver
        );

        wp_enqueue_script(
            'fsf-product-grid',
            $app_url . 'product-grid.js',
            array(),
            $js_ver,
            true
        );

        add_filter( 'script_loader_tag', array( $this, 'add_module_type' ), 10, 3 );
    }

    public function add_module_type( $tag, $handle, $src ) {
        if ( 'fsf-product-grid' === $handle ) {
            $tag = str_replace( ' src', ' type="module" src', $tag );
        }
        return $tag;
    }

    public function render_shortcode( $atts ) {
        return '<div id="fsf-product-grid" class="fsf-product-grid-wrapper"></div>';
    }
}

new FSF_Product_Grid();
