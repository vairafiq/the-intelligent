<?php
/**
 * Train
 *
 * Train AI.
 *
 * @package theIntelligent
 * @version 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Train class.
 */
class theIntelligent_Train {

    function __construct() {
        // add_action( 'init', array( $this, 'create_dot' ), 10, 2 );
        add_action( 'init', [$this, 'eg_schedule_theintelligent'] );
        add_action( 'theintelligent_ai_train_init', [$this, 'train'] );
        add_action( 'theintelligent_ai_train_orders', [$this, 'process_orders'], 10, 2 );
        add_action( 'theintelligent_ai_train_dot', [$this, 'create_dot'] );
        add_action( 'theintelligent_ai_train_products', [$this, 'process_product'], 10, 2 );
        add_action( 'theintelligent_ai_train_finish', [$this, 'finish_training'], 10 );
    }

    function eg_schedule_theintelligent() {
        if ( function_exists( 'as_next_scheduled_action' ) && false === as_next_scheduled_action( 'theintelligent_ai_train_init' ) ) {
            as_schedule_recurring_action( time(), WEEK_IN_SECONDS, 'theintelligent_ai_train_init' );
        }
    }

    function train() {
        $total_pages = self::get_total_pages_orders();
        update_site_option( 'theintelligent-last-training', 'In progress' );

        for ( $page = 1; $page <= $total_pages; $page++ ) {
            as_enqueue_async_action( 'theintelligent_ai_train_orders', [$page, $total_pages] );
        }

        as_enqueue_async_action( 'theintelligent_ai_train_dot', [] );

        $total_pages = self::get_total_pages_products();

        for ( $page = 1; $page <= $total_pages; $page++ ) {
            as_enqueue_async_action( 'theintelligent_ai_train_products', [$page, $total_pages] );
        }

        as_enqueue_async_action( 'theintelligent_ai_train_finish', [] );
    }

    static function get_total_pages_orders() {
        $orders = theIntelligent_Sales::get_count_shop_orders();

        if ( is_null( $orders ) && $orders < 1 ) {
            return;
        }

        $total_pages = $page = 1;

        if ( $orders > 100 ) {
            $total_pages = ceil( $orders / 100 );
        }

        return $total_pages;
    }

    static function get_total_pages_products() {
        $products = theIntelligent_Sales::get_count_products();

        if ( is_null( $products ) && $products < 1 ) {
            return;
        }

        $total_pages = $page = 1;

        if ( $products > 10 ) {
            $total_pages = ceil( $products / 10 );
        }

        return $total_pages;
    }

    function process_orders( $page = 1, $total_pages = 1 ) {
        global $wpdb;

        $start = 0;
        $end   = 100;
        if ( $page > 1 ) {
            $start = $page * 100 - 100;
            $end   = $page * 100;
        }

        $results = $wpdb->get_results( "SELECT ID FROM $wpdb->posts
            WHERE post_type = 'shop_order'
            ORDER BY 'date' DESC
            LIMIT $start, $end
        " );

        foreach ( $results as $result ) {
            $order_id = $result->ID;
            $order    = wc_get_order( $order_id );

            $order_data = $order->get_data();
            $items      = $order->get_items();

            foreach ( $items as $item ) {

                $product_id   = $item->get_product_id();
                $product_name = $item->get_name();

                $order_each = [
                    'User ID'    => $order->get_customer_id(),
                    'Full Name'  => $order->get_billing_first_name() . ' ' . $order->get_billing_last_name(),
                    'Product ID' => $product_id,
                ];

                $this->update_db_index( $order_each );
            }
        }
        return true;
    }

    function create_dot() {
        global $wpdb;
        $indexing = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}theintelligent_indexing", ARRAY_A );

        $sales = [];

        foreach ( $indexing as $index ) {
            $user    = $index["full_name"];
            $product = $index["product_id"];
            if ( isset( $user ) && isset( $product ) && ' ' != $user && ' ' != $product ) {
                $sales[$product]['product'] = $product;
                $sales[$product]['users'][] = $user;
            }
        }

        $dot = theIntelligent_Similarity::dot( call_user_func_array( "array_merge", array_column( $sales, "users" ) ) );

        update_site_option( 'theintelligent_sales', $sales );
        update_site_option( 'theintelligent_dot', $dot );

        return true;
    }

    function process_product( $page = 1, $total_pages = 1 ) {

        $sales = get_site_option( 'theintelligent_sales' );
        $dot   = get_site_option( 'theintelligent_dot' );

        $all_products = get_posts( [
            'post_type'   => 'product',
            'numberposts' => -1,
            'post_status' => 'publish',
            'fields'      => 'ids',
            'order_by'    => 'ids',
            'order'       => 'ASC',
        ] );

        $start = 0;
        $end   = 10;
        if ( $page > 1 ) {
            $start = $page * 10 - 10;
            $end   = $page * 10;
        }

        for ( $i = $start; $i < $end; $i++ ) {
            $product_id = $all_products[$i];

            if ( ! array_key_exists( $product_id, $sales ) ) {
                continue;
            }

            $target = $sales[$product_id]['users'];
            foreach ( $sales as $sale ) {
                $score[$sale['product']] = theIntelligent_Similarity::cosine( $target, $sale['users'], $dot );
            }

            arsort( $score );
            foreach ( $score as $product => $similarity ) {
                if ( $similarity > 0 ) {
                    $this->update_db_similarity( $product_id, $product, $similarity );
                }
            }
        }

        return true;
    }

    function finish_training() {
        update_site_option( 'theintelligent-last-training', time() );
    }

    protected function update_db_index( $order ) {
        global $wpdb;
        $user_id    = $order['User ID'];
        $full_name  = $order['Full Name'];
        $product_id = $order['Product ID'];
        $recID      = $wpdb->get_var( "SELECT id FROM " . ( $wpdb->prefix . 'theintelligent_indexing' ) . " WHERE full_name LIKE '" . $full_name . "' AND product_id LIKE " . $product_id );
        if ( ! $recID ) {
            $wpdb->insert(
                $wpdb->prefix . 'theintelligent_indexing',
                [
                    'user_id'    => $user_id,
                    'full_name'  => $full_name,
                    'product_id' => $product_id,
                ],
                ['%d', '%s', '%d']
            );
        }
    }

    protected function update_db_similarity( $product_1, $product_2, $similarity ) {
        global $wpdb;
        $recID = $wpdb->get_var( "SELECT id FROM " . ( $wpdb->prefix . 'theintelligent_suggestions' ) . " WHERE product_1 LIKE " . $product_1 . " AND product_2 LIKE " . $product_2 );
        if ( $recID ) {
            $wpdb->update(
                $wpdb->prefix . 'theintelligent_suggestions',
                [
                    'similarity' => $similarity,
                ],
                [
                    'id' => $recID,
                ],
                ['%f'],
                ['%d']
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'theintelligent_suggestions',
                [
                    'product_1'  => $product_1,
                    'product_2'  => $product_2,
                    'similarity' => $similarity,
                ],
                ['%d', '%d', '%f']
            );
        }
    }

}