<?php
/**
 * AI product suggession template.
 * Used in after card table view.
 *
 * @author  Exlac
 * @since   0.1
 * @version 1.0
 */


if ( ! defined( 'ABSPATH' ) ) exit;


if ( !empty( $products ) && $products->have_posts() ):

    ?>
        <section class="up-sells upsells product-suggestions products">

            <h2><?php echo esc_attr( $data->titles['theintelligent_after_cart_table_title'] ); ?></h2>
           
            <?php woocommerce_product_loop_start();?>

            <?php while ( $products->have_posts() ): $products->the_post();
                    wc_get_template_part( 'content', 'product' );
                    endwhile;
            ?>

            <?php woocommerce_product_loop_end();?>

        </section>
    <?php endif;?>