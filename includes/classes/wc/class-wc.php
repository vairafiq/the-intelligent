<?php
/**
 * WC
 *
 * WC controller
 *
 * @package theIntelligent
 * @version 1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Admin class.
 */
class theIntelligent_WC_Controller {

    public $positions;
    public $titles;

    public function init() {
        $this->positions            = [
            'after_cart_table'  => esc_attr( get_option( 'theintAfterCartTable', true ) ),
            'after_cart'        => esc_attr( get_option( 'theintAfterCart' ) ),
            'after_checkout'    => esc_attr( get_option( 'theintAfterCheckout', true ) ),
        ];

        $this->titles           = apply_filters( 'theintelligent_titles', [
            'theintelligent_after_cart_table_title' => get_option( 'theintelligent_after_cart_table_title', __( 'Recommended for you', 'theintelligent') ),
        ] );
        // Add main functions
        if ( $this->positions['after_cart_table'] ) {
            add_action( 'woocommerce_after_cart_table', [$this, 'ai_suggestions_after_cart'], 10, 0 );
        }
        if ( $this->positions['after_cart'] ) {
            add_action( 'woocommerce_after_cart', [$this, 'ai_suggestions_after_cart'], 10, 1 );
        }
        if ( $this->positions['after_checkout'] ) {
            add_action( 'woocommerce_after_checkout_form', [$this, 'ai_suggestions_after_checkout'], 10, 1 );
        }

        add_filter( 'shortcode_atts_products', [$this, 'theint_shortcode_atts_products'], 10, 4 );
        add_filter( 'woocommerce_shortcode_products_query', [$this, 'theint_woocommerce_shortcode_products_query'], 10, 2 );

        add_filter( 'woocommerce_related_products', [$this, 'theint_woocommerce_product_related_posts'], 10, 3 );
    }


    public function ai_suggestions_after_checkout(){

        $suggestions = get_option( 'intelligentCheckoutNumber', 3 );

        $products = theIntelligent()->get_suggessions( $suggestions );

        $args = [
            'data'      => $this,
            'products'  => $products['query'],
        ];

        $template = apply_filters( 'theint_suggessions_template', 'ai-suggessions', $args );
        
        theint_load_template( $template,  $args );
    }

    public function ai_suggestions_after_cart(){

        $suggestions = get_option( 'intelligentCartNumber', 3 );

        $products = theIntelligent()->get_suggessions( $suggestions );

        $args = [
            'data'      => $this,
            'products'  => $products['query'],
        ];

        $template = apply_filters( 'theint_suggessions_template', 'ai-suggessions', $args );
        
        theint_load_template( $template,  $args );
    }

    public function theint_shortcode_atts_products( $out, $pairs, $atts, $shortcode ){

        if ( isset ( $atts[ 'thesuggestions_suggessions' ] ) ) {
          $out[ 'theintelligent_suggestions' ] = true; 
        } else {
          $out[ 'theintelligent_suggestions' ] = false; 	
        }
        return $out;
      }

    public function theint_woocommerce_product_related_posts( $related_posts, $product_id, $args ) {
        
        $posts_ids = [];

        if( get_option( 'theintelligent_related_products', false ) ) {
            $products = theIntelligent()->get_suggessions();

            $suggestions_ids = ! empty( $products['ids'] ) ? $products['ids'] : [];

            if( $suggestions_ids ) {

                $posts_ids = get_posts( apply_filters( 'theintelligent_related_products_args', array(
                    'post_type'            => 'product',
                    'ignore_sticky_posts'  => 1,
                    'posts_per_page'       => 4,
                    'post__in'             => $suggestions_ids,
                    'fields'               => 'ids',
                    'orderby'              => 'rand',
                ) ) );
            }
        }
        
        return ! empty( $posts_ids ) ? $posts_ids : $related_posts;
      }

    public function theint_woocommerce_shortcode_products_query( $query_args, $attributes ) {

        if ( isset( $attributes[ 'theintelligent_suggestions' ] ) ) {

            $products = theIntelligent()->get_suggessions();
            $suggestions_ids = ! empty( $products['ids'] ) ? $products['ids'] : [];

            if( $suggestions_ids ) {

                $query_args[ 'post__in' ] = $suggestions_ids;
            }

          }
        return $query_args;
    }
   

}