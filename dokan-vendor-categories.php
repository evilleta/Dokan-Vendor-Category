<?php
/*
Plugin Name: Dokan Vendor Categories
Description: Adds vendor categories to Dokan and enables filtering by category on admin and frontend.
Version: 1.1
Author: I Build Awesome Website
Author URI: https://ibuildawesomewebsite.com
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class Dokan_Vendor_Categories {

    public function __construct() {
        add_action( 'init', [ $this, 'register_vendor_category_taxonomy' ] );
        add_action( 'admin_menu', [ $this, 'add_admin_menu' ] );
        add_action( 'show_user_profile', [ $this, 'add_vendor_category_to_user_profile', '4' ] );
        add_action( 'edit_user_profile', [ $this, 'add_vendor_category_to_user_profile' ] );
        add_action( 'personal_options_update', [ $this, 'save_vendor_category_from_profile' ] );
        add_action( 'edit_user_profile_update', [ $this, 'save_vendor_category_from_profile' ] );
        add_action( 'dokan_seller_meta_fields', [ $this, 'vendor_category_field_dashboard' ] );
        add_action( 'dokan_store_profile_saved', [ $this, 'save_vendor_category_from_dashboard' ] );
        add_shortcode( 'vendor_category_filter', [ $this, 'vendor_category_filter_shortcode' ] );
        add_action( 'dokan_store_listing_filter', [ $this, 'add_category_filter_to_store_list' ] );
        add_filter( 'dokan_get_sellers_args', [ $this, 'filter_store_list_by_category' ] );
    }

    public function register_vendor_category_taxonomy() {
        register_taxonomy( 'vendor_category', 'seller', [
            'labels' => [
                'name' => 'Vendor Categories',
                'singular_name' => 'Vendor Category',
                'add_new_item' => 'Add New Vendor Category',
                'edit_item' => 'Edit Vendor Category',
                'search_items' => 'Search Vendor Categories',
            ],
            'public' => true,
            'hierarchical' => true,
            'show_in_rest' => true,
            'rewrite' => [ 'slug' => 'vendor-category' ],
        ]);
    }

    public function add_admin_menu() {
        add_submenu_page(
            'dokan',
            'Vendor Categories',
            'Vendor Categories',
            'manage_options',
            'edit-tags.php?taxonomy=vendor_category'
        );
    }

    public function add_vendor_category_to_user_profile( $user ) {
        if ( ! dokan_is_user_seller( $user ) ) return;
        $terms = get_terms( [ 'taxonomy' => 'vendor_category', 'hide_empty' => false ] );
        $selected = wp_get_object_terms( $user->ID, 'vendor_category', [ 'fields' => 'ids' ] );
        ?>
        <h3>Vendor Category</h3>
        <table class="form-table">
            <tr>
                <th><label for="vendor_category">Category</label></th>
                <td>
                    <select name="vendor_category" id="vendor_category">
                        <option value="">— Select —</option>
                        <?php foreach ( $terms as $term ) : ?>
                            <option value="<?php echo $term->term_id; ?>" <?php selected( in_array( $term->term_id, $selected ) ); ?>>
                                <?php echo esc_html( $term->name ); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
        </table>
        <?php
    }

    public function save_vendor_category_from_profile( $user_id ) {
        if ( ! current_user_can( 'edit_user', $user_id ) ) return;
        wp_set_object_terms( $user_id, intval( $_POST['vendor_category'] ), 'vendor_category' );
    }

    public function vendor_category_field_dashboard( $store_settings ) {
        $terms = get_terms( [ 'taxonomy' => 'vendor_category', 'hide_empty' => false ] );
        $selected = wp_get_object_terms( get_current_user_id(), 'vendor_category', [ 'fields' => 'ids' ] );
        ?>
        <div class="dokan-form-group">
            <label for="vendor_category" class="form-label"><?php _e( 'Vendor Category', 'dokan' ); ?></label>
            <select name="vendor_category" id="vendor_category" class="dokan-form-control">
                <option value="">— Select —</option>
                <?php foreach ( $terms as $term ) : ?>
                    <option value="<?php echo $term->term_id; ?>" <?php selected( in_array( $term->term_id, $selected ) ); ?>>
                        <?php echo esc_html( $term->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <?php
    }

    public function save_vendor_category_from_dashboard( $store_id ) {
        if ( isset( $_POST['vendor_category'] ) ) {
            wp_set_object_terms( get_current_user_id(), intval( $_POST['vendor_category'] ), 'vendor_category' );
        }
    }

    public function vendor_category_filter_shortcode() {
        $terms = get_terms( [ 'taxonomy' => 'vendor_category', 'hide_empty' => false ] );
        ob_start();
        ?>
        <form method="get" action="">
            <select name="vendor_cat_filter" onchange="this.form.submit()">
                <option value="">Filter by Vendor Category</option>
                <?php foreach ( $terms as $term ) : ?>
                    <option value="<?php echo $term->slug; ?>" <?php selected( $_GET['vendor_cat_filter'] ?? '', $term->slug ); ?>>
                        <?php echo esc_html( $term->name ); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </form>
        <div class="vendor-list">
            <?php
            $args = [
                'role' => 'seller',
                'meta_query' => [],
            ];

            if ( ! empty( $_GET['vendor_cat_filter'] ) ) {
                $vendor_ids = get_objects_in_term( get_term_by( 'slug', sanitize_text_field( $_GET['vendor_cat_filter'] ), 'vendor_category' )->term_id, 'vendor_category' );
                $args['include'] = $vendor_ids;
            }

            $users = get_users( $args );
            foreach ( $users as $user ) {
                $store_info = dokan_get_store_info( $user->ID );
                $store_url = dokan_get_store_url( $user->ID );
                echo '<div class="vendor">';
                if ( ! empty( $store_info['gravatar'] ) ) {
                    echo '<img src="' . esc_url( $store_info['gravatar'] ) . '" alt="" style="width:80px;height:80px;border-radius:50%;margin-bottom:10px;">';
                }
                echo '<h4><a href="' . esc_url( $store_url ) . '">' . esc_html( $store_info['store_name'] ?? $user->display_name ) . '</a></h4>';
                echo '<p>' . esc_html( $store_info['address']['street_1'] ?? '' ) . '</p>';
                echo '</div>';
            }
            ?>
        </div>
        <?php
        return ob_get_clean();
    }

    public function add_category_filter_to_store_list() {
        $terms = get_terms( [ 'taxonomy' => 'vendor_category', 'hide_empty' => false ] );
        $selected = $_GET['vendor_cat_filter'] ?? '';
        ?>
        <div class="vendor-category-filter" style="margin-bottom: 20px;">
            <form method="get">
                <?php foreach ( $_GET as $key => $val ) {
                    if ( $key !== 'vendor_cat_filter' ) {
                        echo '<input type="hidden" name="' . esc_attr( $key ) . '" value="' . esc_attr( $val ) . '">';
                    }
                } ?>
                <select name="vendor_cat_filter" onchange="this.form.submit()" style="padding: 5px; min-width: 200px;">
                    <option value="">Filter by Vendor Category</option>
                    <?php foreach ( $terms as $term ) : ?>
                        <option value="<?php echo esc_attr( $term->slug ); ?>" <?php selected( $selected, $term->slug ); ?>>
                            <?php echo esc_html( $term->name ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>
        </div>
        <?php
    }

    public function filter_store_list_by_category( $args ) {
        if ( ! empty( $_GET['vendor_cat_filter'] ) ) {
            $term = get_term_by( 'slug', sanitize_text_field( $_GET['vendor_cat_filter'] ), 'vendor_category' );
            if ( $term ) {
                $vendor_ids = get_objects_in_term( $term->term_id, 'vendor_category' );
                if ( ! empty( $vendor_ids ) ) {
                    $args['include'] = $vendor_ids;
                } else {
                    $args['include'] = [ 0 ];
                }
            }
        }
        return $args;
    }
}

new Dokan_Vendor_Categories();
