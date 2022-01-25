<?php
if ( ! defined( 'ABSPATH' ) ) {
    die;
}

if ( ! class_exists( 'theIntelligent' ) ) {
    final class theIntelligent {

        /**
         * theInt_admin Object.
         *
         * @var object|theInt_admin
         * @since 1.0
         */
        public $admin;

        /**
         * theIntelligent_WC_Controller Object.
         *
         * @var object|theIntelligent_WC_Controller
         * @since 1.0
         */
        public $wc;

        /**
         * theIntelligent_Train Object.
         *
         * @var object|theIntelligent_Train
         * @since 1.0
         */
        public $train;

        public $plugin_name;
        public $plugin_version;
        public $ajax_url;
        public $suggestions_per_page;
        public $positions;
        public $titles;
        public $suggestions = [];
        public static $instance;
        public static $base_dir;
        public static $base_url;

        public static function instance() {

            if ( ! isset( self::$instance ) && ! ( self::$instance instanceof theIntelligent ) ) {
                self::$instance = new theIntelligent();
                self::$instance->init();

                $theintelligent_settings = new theIntelligent_Admin();
                self::$instance->admin   = $theintelligent_settings->init();

                // wc controller
                $wc = new theIntelligent_WC_Controller();
                self::$instance->wc   = $wc->init();

            }

            return self::$instance;
        }

        function __construct() {

        }

        private function init() {
            add_action( 'plugins_loaded', [$this, 'load_textdomain'], 20 );

            self::$base_dir = plugin_dir_path( theInt_PLUGIN_FILE );
            self::$base_url = plugin_dir_url( theInt_PLUGIN_FILE );

            $this->includes();

            $this->plugin_name          = plugin_basename( theInt_PLUGIN_FILE );
            $this->plugin_version       = '0.1';
            $this->ajax_url             = admin_url( 'admin-ajax.php' );
            $this->suggestions_per_page = [
                'posts_show_after_cart'     => get_option( 'intelligentCartNumber' ),
                'posts_show_after_checkout' => get_option( 'intelligentCartNumber' ),
            ];
            
            $this->titles               = apply_filters( 'theintelligent_titles', [
                'theintelligent_after_cart_table_title' => get_option( 'theintelligent_after_cart_table_title', __( 'Recommended for you', 'theintelligent') ),
            ] );

            // Add admin notices
            add_action( 'admin_notices', [$this, 'wt_admin_notices'] );

            // Enqueue assets
            add_action( 'admin_enqueue_scripts', [$this, 'enqueue_scripts'] );

            // Register ajax calls
            add_action( 'wp_ajax_train_data', [$this, 'force_retrain'] );
            add_action( 'woocommerce_thankyou', [$this, 'force_after_an_order'] );

            // Add settings links
            add_filter( "plugin_action_links_$this->plugin_name", [$this, 'add_settings_links'] );

            add_filter( 'plugin_row_meta', [$this, 'plugin_row_meta'], 10, 2 );

            
            $this->init_setup();
        }

        /**
         * It loads plugin text domain
         *
         * @since 1.0.0
         */
        public function load_textdomain() {
            load_plugin_textdomain( 'theintelligent', false, theInt_LANG_PATH );
        }

        public function includes() {

            require_once self::$base_dir . '/vendor/autoload.php';
            require self::$base_dir . '/includes/helpers.php';
            require self::$base_dir . '/includes/classes/class-sales.php';
            require self::$base_dir . '/includes/classes/class-train.php';
            require self::$base_dir . '/includes/classes/class-similarity.php';
            require self::$base_dir . '/includes/classes/class-admin.php';
            require self::$base_dir . '/includes/classes/wc/class-wc.php';
            require self::$base_dir . '/includes/lib/settings/index.php';

        }

        /**
         * Runs on activation
         */
        public function activate() {
            // Set thank you notice transient
            set_site_transient( 'wt-admin-notices-on-install', true, 5 );
            set_site_transient( 'wt-init-indexing', true, 5 );
            set_site_transient( 'wt-admin-notices-after-one-month', true, 30 * DAY_IN_SECONDS );
            set_site_transient( 'wt-admin-notices-after-two-months', true, 60 * DAY_IN_SECONDS );
            $this->maybe_create_suggestions_db_table();
            $this->maybe_create_indexing_db_table();
            flush_rewrite_rules();
        }

        /**
         * Runs on load
         */
        public function init_setup() {
            $this->maybe_create_suggestions_db_table();
            $this->maybe_create_indexing_db_table();
            self::$instance->train = new theIntelligent_Train();
            // clears up old data
            // delete_site_option( 'theintelligent-associator' );
            // delete_site_option( 'theintelligent-product-sales' );
        }

        /**
         * Runs on deactivation
         */
        public function deactivate() {
            flush_rewrite_rules();
        }

        /**
         * Adds admin notices
         */
        public function wt_admin_notices() {
            // Adds notice of require WooCommerce plugin
            if ( ! class_exists( 'WooCommerce' ) ) {
                ?>
				<div class="notice-warning settings-error notice">
				   <p><?php _e( 'Please install and activate WooCommerce first, then click "Force Update AI" or wait for the next purchase.', 'theintelligent' );?></p>
				</div>
				<?php
            }
            // Check and display on install notices
            if ( get_site_transient( 'wt-admin-notices-on-install' ) ) {
                ?>
                <div class="notice notice-success is-dismissible theintelligent-theme-updater-notice theintelligent-theme-updater-notice-pro">
                    <h2><?php _e( 'Thank you for installing! 🚀 The smart AI has been deployed and it will process the sales in the background...', 'theintelligent' )?></h2>
                    <p><?php _e( 'Meanwhile, start by updating the <a href="/wp-admin/admin.php?page=theintelligent">Settings</a> to best match your theme style.', 'theintelligent' )?></p>
                    <div class="theintelligent-updater-action">
                        <a class="theintelligent-btn theintelligent-btn-updgrade" href="/wp-admin/admin.php?page=wc-status&tab=action-scheduler&status=pending&s=theintelligent&action=-1&paged=1&action2=-1" target="_blank"><?php esc_html_e( 'Track Progress', 'onelisting' );?></a>
                    </div>
                </div>
		        <?php
/* Delete transient, only display this notice once. */
                delete_site_transient( 'wt-admin-notices-on-install' );
            }
        }

        /**
         * Enqueues admin scripts
         */
        public function enqueue_scripts() {
            wp_enqueue_style( 'theintelligent_main_css', plugin_dir_url( theInt_PLUGIN_FILE ) . 'assets/theintelligent-main.css', [], $this->plugin_version );
            wp_enqueue_script( 'theintelligent_main_js', plugin_dir_url( theInt_PLUGIN_FILE ) . 'assets/theintelligent-main.js', [], $this->plugin_version, true );
        }

        /**
         * Show row meta on the plugin screen.
         *
         * @param mixed $links Plugin Row Meta.
         * @param mixed $file  Plugin Base file.
         *
         * @return array
         */
        public static function plugin_row_meta( $links, $file ) {
            if ( plugin_basename( theInt_PLUGIN_FILE ) !== $file ) {
                return $links;
            }
            $nonce    = wp_create_nonce( "wt-force-train" );
            $row_meta = [
                'train_data' => '<a href="#" id="train_data" data-nonce="' . $nonce . '">Force Run AI</a>',
            ];

            return array_merge( $links, $row_meta );
        }

        /**
         * Adds admin links on plugin page
         */
        public function add_settings_links( $links ) {
            $setting_links = [
                '<a href="/wp-admin/admin.php?page=theintelligent" target="">Settings</a>',
            ];

            return array_merge( $setting_links, $links );
        }

        /**
         * Creates suggestions table on activation
         */
        public function maybe_create_suggestions_db_table() {
            if ( get_option( 'theintelligent_suggestions_db_version' ) ) {
                return;
            }

            global $wpdb;
            $table_name                            = $wpdb->prefix . "theintelligent_suggestions";
            $theintelligent_suggestions_db_version = '1.0.0';
            $charset_collate                       = $wpdb->get_charset_collate();

            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {

                $sql = "CREATE TABLE $table_name (
			            `id` int NOT NULL AUTO_INCREMENT,
			            `product_1` int NOT NULL,
			            `product_2` int NOT NULL,
			            `similarity` float NOT NULL,
			            PRIMARY KEY  (ID)
			    )    $charset_collate;";

                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                dbDelta( $sql );
                add_option( 'theintelligent_suggestions_db_version', $theintelligent_suggestions_db_version );
            }
        }

        /**
         * Creates indexing table on activation
         */
        public function maybe_create_indexing_db_table() {
            if ( get_option( 'theintelligent_indexing_db_version' ) ) {
                return;
            }

            global $wpdb;
            $table_name                         = $wpdb->prefix . "theintelligent_indexing";
            $theintelligent_indexing_db_version = '1.0.0';
            $charset_collate                    = $wpdb->get_charset_collate();

            if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {

                $sql = "CREATE TABLE $table_name (
			            `id` int NOT NULL AUTO_INCREMENT,
			            `user_id` int NOT NULL,
			            `full_name` text NOT NULL,
			            `product_id` int NOT NULL,
			            PRIMARY KEY  (ID)
			    )    $charset_collate;";

                require_once ABSPATH . 'wp-admin/includes/upgrade.php';
                dbDelta( $sql );
                add_option( 'theintelligent_indexing_db_version', $theintelligent_indexing_db_version );
            }
        }


        private function unscheduled() {
            as_unschedule_all_actions( 'theintelligent_ai_train_init' );
            as_unschedule_all_actions( 'theintelligent_ai_train_orders' );
            as_unschedule_all_actions( 'theintelligent_ai_train_products' );
        }

        public function force_after_an_order() {
            $this->unscheduled();
        }

        /**
         * Cleares up all queued training cron jobs
         * so the plugin can create new ones on init
         */
        public function force_retrain() {
            
            $nonce = ! empty( $_POST[ 'nonce' ] ) ? $_POST[ 'nonce' ] : '';

            if( wp_verify_nonce( $nonce, 'theint_nonce' ) ) {

                $this->unscheduled();
                
                wp_send_json_success('success');

            }else{

                wp_send_json_error('Blocked for security reason!');
            }
        }


        public function get_suggessions( $suggestions_per_page = 3 ) {
            global $wpdb;

            // get all products in cart
            $products_in_cart = '';

            if ( ! wp_doing_ajax() ) {
                $products_in_cart = theIntelligent_Sales::get_cart_items();
            }
            
            if( ! $products_in_cart ) {
                return [];
            }

            
            // get all suggestions based off all products
            $suggestions           = [];
            $final_predictions     = [];
            $number_of_suggestions = 0;

            if ( get_site_option( 'theintelligent-last-training', false ) && ! empty( get_site_option( 'theintelligent-last-training' ) ) ) {
                foreach ( $products_in_cart as $product_id ) {
                    // for each combination AI predict

                    // Retrieve suggestions from the DB
                    $predictions = $wpdb->get_results(
                        $wpdb->prepare( "SELECT product_2 FROM {$wpdb->prefix}theintelligent_suggestions WHERE product_1=%d ORDER BY `similarity` DESC", $product_id ), ARRAY_A
                    );
                    // add prediction to suggestions array
                    foreach ( $predictions as $prediction ) {
                        foreach ( $prediction as $key => $value ) {
                            $suggestions[] = $value;
                        }
                    }
                }

                if ( $suggestions ) {
                    // order suggestions by most frequest first
                    $sorted_data = theIntelligent_Sales::get_most_frequent( $suggestions );

                    // remove products from suggestions for various reasons
                    $final_predictions = [];
                    foreach ( $sorted_data as $product_id ) {

                        // get options
                        $optionOutOfStock = esc_attr( get_option( 'theintelligent_exclude_out_of_stock' ) );
                        $optionBackorders = esc_attr( get_option( 'theintelligent_include_backorders' ) );
                        $hideProduct      = false;
                        // if hide out of stock option is active
                        if ( $optionOutOfStock ) {
                            $product = wc_get_product( $product_id );
                            $status  = $product->get_stock_status();

                            // if out of stock
                            if ( 'instock' != $status ) {
                                // if include backorders option is acitve
                                if ( $optionBackorders ) {
                                    // if actual backorders are not allowed
                                    if ( 'onbackorder' != $status ) {
                                        $hideProduct = true;
                                    }
                                } else {
                                    $hideProduct = true;
                                }
                            }
                        }
                        // remove products from suggestions that are already in cart
                        if ( ! $hideProduct && ! in_array( $product_id, $products_in_cart ) ) {
                            $final_predictions[] = $product_id;
                        }
                    }
                    $number_of_suggestions = count( $final_predictions );
                }
            }

            /**
             * If number of suggestions not sufficient
             * then get remaining number of products
             * randomly, based off related categories
             */
            if ( $number_of_suggestions < $suggestions_per_page ) {
                $needed_sugestions = $suggestions_per_page - $number_of_suggestions;

                $all_categories = [];
                foreach ( $products_in_cart as $product_in_cart ) {
                    $categories = get_the_terms( $product_in_cart, 'product_cat' );

                    if ( $categories ) {
                        foreach ( $categories as $cat ) {
                            $all_categories[] = $cat->slug;
                        }
                    }
                }
                array_unique( $all_categories );

                if ( $all_categories ) {
                    $args = [
                        'post_type'      => 'product',
                        'post_status'    => 'publish',
                        'posts_per_page' => $needed_sugestions,
                        'post__not_in'   => $products_in_cart,
                        'tax_query'      => [
                            [
                                'taxonomy' => 'product_cat',
                                'field'    => 'slug',
                                'terms'    => $all_categories,
                            ],
                        ],
                    ];

                    $fallback_products = new WP_Query( $args );
                    if ( $fallback_products->have_posts() ):
                        while ( $fallback_products->have_posts() ): $fallback_products->the_post();
                            $final_predictions[] = get_the_ID();
                        endwhile;
                    endif;

                    // to try this instead of the above
                    // $post_ids = wp_list_pluck( $latest->posts, 'ID' );
                }
            }

            /**
             * Creates final display query using all found predictions
             * Uses default WooCommerce product template
             */
            $args = [
                'post_type'      => 'product',
                'post_status'    => 'publish',
                'post__in'       => $final_predictions,
                'orderby'        => 'post__in',
                'posts_per_page' => $suggestions_per_page,
            ];

            $display_products = new WP_Query( $args );

            $this->suggestions = $display_products;

            update_option( 'theint_suggestions', $display_products );

            return [
                'query' => $display_products, 
                'ids'   => $final_predictions, 
            ];
        }

    }

    function theIntelligent() {
        return theIntelligent::instance();
    }

    $theintelligent = theIntelligent();

    // activation
    register_activation_hook( theInt_PLUGIN_FILE, [$theintelligent, 'activate'] );

    // deactivation
    register_deactivation_hook( theInt_PLUGIN_FILE, [$theintelligent, 'deactivate'] );

}