<?php
/**
 * Plugin Name: FSF Product Grid
 * Description: Embeds a filterable grid of member-recommended products with an admin UI for managing products.
 * Version:     1.1.0
 * Author:      Rhumbline AI
 * License:     GPL v2 or later
 * Text Domain: fsf-product-grid
 */

defined( 'ABSPATH' ) || exit;

class FSF_Product_Grid {

    private $data_file;
    private $images_dir;
    private $images_url;

    public function __construct() {
        $this->data_file  = plugin_dir_path( __FILE__ ) . 'app/products.json';
        $this->images_dir = plugin_dir_path( __FILE__ ) . 'images/';
        $this->images_url = plugin_dir_url( __FILE__ ) . 'images/';

        add_shortcode( 'fsf_product_grid', array( $this, 'render_shortcode' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
        add_action( 'admin_menu', array( $this, 'admin_menu' ) );
        add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
    }

    /* ------------------------------------------------------------------ */
    /*  DATA HELPERS                                                       */
    /* ------------------------------------------------------------------ */

    private function read_products() {
        if ( ! file_exists( $this->data_file ) ) {
            return array();
        }
        $json = file_get_contents( $this->data_file );
        $data = json_decode( $json, true );
        return is_array( $data ) ? $data : array();
    }

    private function write_products( $products ) {
        $dir = dirname( $this->data_file );
        if ( ! is_dir( $dir ) ) {
            wp_mkdir_p( $dir );
        }
        file_put_contents(
            $this->data_file,
            wp_json_encode( $products, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE ) . "\n"
        );
    }

    private function get_categories() {
        $products = $this->read_products();
        $cats = array();
        foreach ( $products as $p ) {
            if ( ! empty( $p['category'] ) && ! in_array( $p['category'], $cats, true ) ) {
                $cats[] = $p['category'];
            }
        }
        sort( $cats );
        return $cats;
    }

    private function copy_media_to_plugin( $attachment_id ) {
        $file_path = get_attached_file( $attachment_id );
        if ( ! $file_path || ! file_exists( $file_path ) ) {
            return '';
        }

        $ext      = pathinfo( $file_path, PATHINFO_EXTENSION );
        $filename = sanitize_file_name( pathinfo( $file_path, PATHINFO_FILENAME ) ) . '.' . $ext;

        if ( ! is_dir( $this->images_dir ) ) {
            wp_mkdir_p( $this->images_dir );
        }

        $dest = $this->images_dir . $filename;

        // Avoid overwriting — append a suffix if needed
        $counter = 1;
        while ( file_exists( $dest ) ) {
            $dest = $this->images_dir . pathinfo( $filename, PATHINFO_FILENAME ) . '-' . $counter . '.' . $ext;
            $counter++;
        }

        copy( $file_path, $dest );
        return basename( $dest );
    }

    /* ------------------------------------------------------------------ */
    /*  FRONTEND                                                           */
    /* ------------------------------------------------------------------ */

    public function enqueue_assets() {
        global $post;
        if ( ! $post || ! has_shortcode( $post->post_content, 'fsf_product_grid' ) ) {
            return;
        }

        $app_url  = plugin_dir_url( __FILE__ ) . 'app/';
        $app_path = plugin_dir_path( __FILE__ ) . 'app/';
        $css_ver  = file_exists( $app_path . 'product-grid.css' ) ? filemtime( $app_path . 'product-grid.css' ) : '1.1.0';
        $js_ver   = file_exists( $app_path . 'product-grid.js' )  ? filemtime( $app_path . 'product-grid.js' )  : '1.1.0';

        wp_enqueue_style( 'fsf-product-grid', $app_url . 'product-grid.css', array(), $css_ver );
        wp_enqueue_script( 'fsf-product-grid', $app_url . 'product-grid.js', array(), $js_ver, true );

        add_filter( 'script_loader_tag', array( $this, 'add_module_type' ), 10, 3 );

        add_action( 'wp_footer', array( $this, 'inject_config' ), 1 );
    }

    public function inject_config() {
        $config = array(
            'imagesUrl' => esc_url_raw( $this->images_url ),
        );
        echo '<script>window.fsfConfig=' . wp_json_encode( $config ) . ';</script>';
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

    /* ------------------------------------------------------------------ */
    /*  ADMIN: MENU                                                        */
    /* ------------------------------------------------------------------ */

    public function admin_menu() {
        add_menu_page(
            'Product Grid',
            'Product Grid',
            'manage_options',
            'fsf-products',
            array( $this, 'render_admin_page' ),
            'dashicons-grid-view',
            26
        );
    }

    public function enqueue_admin_assets( $hook ) {
        if ( 'toplevel_page_fsf-products' !== $hook ) {
            return;
        }
        wp_enqueue_media();
    }

    /* ------------------------------------------------------------------ */
    /*  ADMIN: ACTION HANDLER                                              */
    /* ------------------------------------------------------------------ */

    public function handle_admin_actions() {
        if ( empty( $_POST['fsf_action'] ) ) {
            return;
        }
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( 'Unauthorized' );
        }
        check_admin_referer( 'fsf_product_action' );

        $products = $this->read_products();
        $action   = sanitize_text_field( $_POST['fsf_action'] );

        if ( 'add' === $action || 'edit' === $action ) {
            $product = array(
                'title'       => sanitize_text_field( $_POST['fsf_title'] ?? '' ),
                'url'         => esc_url_raw( $_POST['fsf_url'] ?? '' ),
                'description' => sanitize_textarea_field( $_POST['fsf_description'] ?? '' ),
                'category'    => sanitize_text_field( $_POST['fsf_category'] ?? '' ),
                'ogImage'     => '',
            );

            // Handle new category
            if ( '__new__' === $product['category'] && ! empty( $_POST['fsf_category_new'] ) ) {
                $product['category'] = sanitize_text_field( $_POST['fsf_category_new'] );
            }

            // Handle image: media library upload takes priority
            $attachment_id = absint( $_POST['fsf_image_id'] ?? 0 );
            if ( $attachment_id ) {
                $filename = $this->copy_media_to_plugin( $attachment_id );
                if ( $filename ) {
                    $product['ogImage'] = $filename;
                }
            } elseif ( ! empty( $_POST['fsf_image_url'] ) ) {
                $product['ogImage'] = esc_url_raw( $_POST['fsf_image_url'] );
            }

            if ( 'edit' === $action ) {
                $index = absint( $_POST['fsf_index'] );
                if ( isset( $products[ $index ] ) ) {
                    // Keep existing image if none uploaded
                    if ( empty( $product['ogImage'] ) && ! empty( $products[ $index ]['ogImage'] ) ) {
                        $product['ogImage'] = $products[ $index ]['ogImage'];
                    }
                    $products[ $index ] = $product;
                }
            } else {
                $products[] = $product;
            }

            $this->write_products( $products );
            $msg = 'edit' === $action ? 'updated' : 'added';
            wp_safe_redirect( admin_url( "admin.php?page=fsf-products&{$msg}=1" ) );
            exit;
        }

        if ( 'delete' === $action ) {
            $index = absint( $_POST['fsf_index'] );
            if ( isset( $products[ $index ] ) ) {
                // Delete associated local image
                $img = $products[ $index ]['ogImage'] ?? '';
                if ( $img && ! filter_var( $img, FILTER_VALIDATE_URL ) ) {
                    $path = $this->images_dir . $img;
                    if ( file_exists( $path ) ) {
                        unlink( $path );
                    }
                }
                array_splice( $products, $index, 1 );
                $this->write_products( $products );
            }
            wp_safe_redirect( admin_url( 'admin.php?page=fsf-products&deleted=1' ) );
            exit;
        }
    }

    /* ------------------------------------------------------------------ */
    /*  ADMIN: PAGE RENDERER                                               */
    /* ------------------------------------------------------------------ */

    public function render_admin_page() {
        $products   = $this->read_products();
        $categories = $this->get_categories();
        $show_form  = isset( $_GET['action'] ) && in_array( $_GET['action'], array( 'add', 'edit' ), true );
        $edit_index = isset( $_GET['index'] ) ? absint( $_GET['index'] ) : -1;
        $edit_data  = ( 'edit' === ( $_GET['action'] ?? '' ) && isset( $products[ $edit_index ] ) )
            ? $products[ $edit_index ]
            : null;

        if ( $show_form ) {
            $this->render_product_form( $edit_data, $edit_index, $categories );
            return;
        }

        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline">Product Grid — All Products</h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=fsf-products&action=add' ) ); ?>" class="page-title-action">Add New Product</a>
            <hr class="wp-header-end">

            <?php if ( ! empty( $_GET['added'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>Product added.</p></div>
            <?php elseif ( ! empty( $_GET['updated'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>Product updated.</p></div>
            <?php elseif ( ! empty( $_GET['deleted'] ) ) : ?>
                <div class="notice notice-success is-dismissible"><p>Product deleted.</p></div>
            <?php endif; ?>

            <p class="description"><?php echo count( $products ); ?> products. Data file: <code>app/products.json</code></p>

            <table class="wp-list-table widefat striped" style="margin-top: 12px;">
                <thead>
                    <tr>
                        <th style="width:50px;">Image</th>
                        <th>Title</th>
                        <th>Category</th>
                        <th>URL</th>
                        <th style="width:120px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( empty( $products ) ) : ?>
                    <tr><td colspan="5">No products yet. <a href="<?php echo esc_url( admin_url( 'admin.php?page=fsf-products&action=add' ) ); ?>">Add one.</a></td></tr>
                <?php else : ?>
                    <?php foreach ( $products as $i => $p ) : ?>
                        <tr>
                            <td>
                                <?php
                                $img = $p['ogImage'] ?? '';
                                if ( $img ) {
                                    $src = filter_var( $img, FILTER_VALIDATE_URL ) ? $img : $this->images_url . $img;
                                    echo '<img src="' . esc_url( $src ) . '" style="width:40px;height:40px;object-fit:contain;border-radius:4px;" />';
                                } else {
                                    echo '<span style="display:inline-block;width:40px;height:40px;background:#e5e7eb;border-radius:4px;text-align:center;line-height:40px;font-weight:700;color:#6b7280;">' . esc_html( mb_substr( $p['title'], 0, 1 ) ) . '</span>';
                                }
                                ?>
                            </td>
                            <td><strong><?php echo esc_html( $p['title'] ); ?></strong></td>
                            <td><?php echo esc_html( $p['category'] ?? '' ); ?></td>
                            <td>
                                <?php if ( ! empty( $p['url'] ) ) : ?>
                                    <a href="<?php echo esc_url( $p['url'] ); ?>" target="_blank" rel="noopener"><?php echo esc_html( wp_parse_url( $p['url'], PHP_URL_HOST ) ); ?></a>
                                <?php else : ?>
                                    &mdash;
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="<?php echo esc_url( admin_url( 'admin.php?page=fsf-products&action=edit&index=' . $i ) ); ?>">Edit</a>
                                |
                                <form method="post" style="display:inline;" onsubmit="return confirm('Delete this product?');">
                                    <?php wp_nonce_field( 'fsf_product_action' ); ?>
                                    <input type="hidden" name="fsf_action" value="delete">
                                    <input type="hidden" name="fsf_index" value="<?php echo esc_attr( $i ); ?>">
                                    <button type="submit" class="button-link" style="color:#b32d2e;cursor:pointer;">Delete</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }

    /* ------------------------------------------------------------------ */
    /*  ADMIN: ADD/EDIT FORM                                               */
    /* ------------------------------------------------------------------ */

    private function render_product_form( $data, $index, $categories ) {
        $is_edit = ! empty( $data );
        $title   = $data['title'] ?? '';
        $url     = $data['url'] ?? '';
        $desc    = $data['description'] ?? '';
        $cat     = $data['category'] ?? '';
        $img     = $data['ogImage'] ?? '';

        $img_preview = '';
        if ( $img ) {
            $img_preview = filter_var( $img, FILTER_VALIDATE_URL ) ? $img : $this->images_url . $img;
        }

        ?>
        <div class="wrap">
            <h1><?php echo $is_edit ? 'Edit Product' : 'Add New Product'; ?></h1>
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=fsf-products' ) ); ?>">&larr; Back to Products</a>

            <form method="post" style="max-width: 700px; margin-top: 16px;">
                <?php wp_nonce_field( 'fsf_product_action' ); ?>
                <input type="hidden" name="fsf_action" value="<?php echo $is_edit ? 'edit' : 'add'; ?>">
                <?php if ( $is_edit ) : ?>
                    <input type="hidden" name="fsf_index" value="<?php echo esc_attr( $index ); ?>">
                <?php endif; ?>

                <table class="form-table">
                    <tr>
                        <th><label for="fsf_title">Title <span style="color:#d63638;">*</span></label></th>
                        <td><input type="text" id="fsf_title" name="fsf_title" class="widefat" required value="<?php echo esc_attr( $title ); ?>" placeholder="e.g. Amlactin - Body Lotion"></td>
                    </tr>
                    <tr>
                        <th><label for="fsf_url">Product URL</label></th>
                        <td><input type="url" id="fsf_url" name="fsf_url" class="widefat" value="<?php echo esc_url( $url ); ?>" placeholder="https://www.example.com/"></td>
                    </tr>
                    <tr>
                        <th><label for="fsf_description">Description</label></th>
                        <td><textarea id="fsf_description" name="fsf_description" class="widefat" rows="3" placeholder="Short product description..."><?php echo esc_textarea( $desc ); ?></textarea></td>
                    </tr>
                    <tr>
                        <th><label for="fsf_category">Category</label></th>
                        <td>
                            <select id="fsf_category" name="fsf_category" style="width:100%;max-width:400px;">
                                <option value="">— Select —</option>
                                <?php foreach ( $categories as $c ) : ?>
                                    <option value="<?php echo esc_attr( $c ); ?>" <?php selected( $cat, $c ); ?>><?php echo esc_html( $c ); ?></option>
                                <?php endforeach; ?>
                                <option value="__new__">+ Add new category…</option>
                            </select>
                            <div id="fsf-new-cat-wrap" style="display:none;margin-top:8px;">
                                <input type="text" id="fsf_category_new" name="fsf_category_new" class="regular-text" placeholder="New category name">
                            </div>
                            <script>
                                document.getElementById('fsf_category').addEventListener('change', function() {
                                    document.getElementById('fsf-new-cat-wrap').style.display = this.value === '__new__' ? 'block' : 'none';
                                });
                            </script>
                        </td>
                    </tr>
                    <tr>
                        <th>Product Image</th>
                        <td>
                            <div id="fsf-image-preview" style="margin-bottom:10px;">
                                <?php if ( $img_preview ) : ?>
                                    <img src="<?php echo esc_url( $img_preview ); ?>" style="max-width:200px;height:auto;border:1px solid #ddd;border-radius:6px;">
                                <?php endif; ?>
                            </div>

                            <input type="hidden" id="fsf_image_id" name="fsf_image_id" value="">
                            <input type="hidden" id="fsf_image_url" name="fsf_image_url" value="<?php echo esc_url( $img_preview ); ?>">

                            <button type="button" id="fsf-upload-btn" class="button">
                                <?php echo $img_preview ? 'Change Image' : 'Upload Image'; ?>
                            </button>
                            <?php if ( $img_preview ) : ?>
                                <button type="button" id="fsf-remove-btn" class="button" style="margin-left:4px;">Remove Image</button>
                            <?php endif; ?>

                            <p class="description">Choose from the Media Library. The image will be copied into the plugin's images folder.</p>

                            <script>
                            (function(){
                                var frame;
                                document.getElementById('fsf-upload-btn').addEventListener('click', function(e) {
                                    e.preventDefault();
                                    if (frame) { frame.open(); return; }
                                    frame = wp.media({
                                        title: 'Select Product Image',
                                        button: { text: 'Use this image' },
                                        multiple: false
                                    });
                                    frame.on('select', function() {
                                        var attachment = frame.state().get('selection').first().toJSON();
                                        document.getElementById('fsf_image_id').value = attachment.id;
                                        document.getElementById('fsf_image_url').value = attachment.url;
                                        var preview = document.getElementById('fsf-image-preview');
                                        preview.innerHTML = '<img src="' + attachment.url + '" style="max-width:200px;height:auto;border:1px solid #ddd;border-radius:6px;">';
                                        e.target.textContent = 'Change Image';
                                    });
                                    frame.open();
                                });

                                var removeBtn = document.getElementById('fsf-remove-btn');
                                if (removeBtn) {
                                    removeBtn.addEventListener('click', function(e) {
                                        e.preventDefault();
                                        document.getElementById('fsf_image_id').value = '';
                                        document.getElementById('fsf_image_url').value = '';
                                        document.getElementById('fsf-image-preview').innerHTML = '';
                                        document.getElementById('fsf-upload-btn').textContent = 'Upload Image';
                                        this.remove();
                                    });
                                }
                            })();
                            </script>
                        </td>
                    </tr>
                </table>

                <p class="submit">
                    <input type="submit" class="button button-primary" value="<?php echo $is_edit ? 'Update Product' : 'Add Product'; ?>">
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=fsf-products' ) ); ?>" class="button">Cancel</a>
                </p>
            </form>
        </div>
        <?php
    }
}

new FSF_Product_Grid();
