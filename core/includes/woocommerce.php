<?php 
/**
 * Theme Kidimat
 *
 * WooCommerce functions and definitions
 *
 * @package WordPress
 * @subpackage Kidimat Themes
 * @author WAP <contact@webagenceparis.com>
 *
 */

/**
 * Change Price Filter Widget Increment (from 10 to 1)
 */ 
function change_price_filter_step() {
	return 0.1;
}
add_filter( 'woocommerce_price_filter_widget_step', 'change_price_filter_step', 10, 3 );

/**
 * Change the breadcrumb separator
 */
add_filter( 'woocommerce_breadcrumb_defaults', 'wcc_change_breadcrumb_delimiter' );
function wcc_change_breadcrumb_delimiter( $defaults ) {
	// Change the breadcrumb delimeter from '/' to '>'
	$defaults['delimiter'] = ' &gt; ';
	return $defaults;
}

/**
 * Ajouter le role de "client professionnel"
 */
add_role(
	'client_professionnel',
	_('Client Professionnel'),
	array(
		'read' => true,
	)
);

/**
 *  Ajouter le champs prix pro à la page création de produit
 */ 
add_action('woocommerce_product_options_pricing', 'wc_cost_product_field');

function wc_cost_product_field(){
	woocommerce_wp_text_input( array( 'id' => 'prix_pro', 'class' => 'wc_input_price_short', 'label' => __( 'Prix Pro', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')' ) );
}

/**
 *  Ajouter le champs prix pro aux variations de produit
 */ 
add_action( 'woocommerce_variation_options_pricing', 'bbloomer_add_prix_pro_to_variations', 10, 3 );
 
function bbloomer_add_prix_pro_to_variations( $loop, $variation_data, $variation ) {
   woocommerce_wp_text_input( array(
		'id' => 'prix_pro[' . $loop . ']',
		'class' => 'short',
		'label' => __( 'Tarif_professionnel', 'woocommerce' ) . ' (' . get_woocommerce_currency_symbol() . ')',
		'value' => get_post_meta( $variation->ID, 'prix_pro', true )
	));
}
add_action( 'save_post', 'wc_cost_save_product' );
function wc_cost_save_product( $product_id ) {

	// stop the quick edit interferring as this will stop it saving properly, when a user uses quick edit feature
	if (wp_verify_nonce($_POST['_inline_edit'], 'inlineeditnonce'))
		return;

	// If this is a auto save do nothing, we only save when update button is clicked
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
		return;
	if ( isset( $_POST['prix_pro'] ) ) {
		if ( is_numeric( $_POST['prix_pro'] ) )
			update_post_meta( $product_id, 'prix_pro', $_POST['prix_pro'] );
	} else delete_post_meta( $product_id, 'prix_pro' );
}

/**
 * 	Save Professionnal price on product variation save
 */
add_action( 'woocommerce_save_product_variation', 'bbloomer_save_prix_pro_variations', 10, 2 );
 
function bbloomer_save_prix_pro_variations( $variation_id, $i ) {
   $prix_pro = $_POST['prix_pro'][$i];
	 if ( isset( $prix_pro ) ) {
		if ( is_numeric( $prix_pro ) )
			update_post_meta( $variation_id, 'prix_pro', esc_attr( $prix_pro ) );
	} else delete_post_meta( $variation_id, 'prix_pro' );
}

/**
 * 	Store Professionnal prices value into variation data
 */

add_filter( 'woocommerce_available_variation', 'wc_proprice_add_custom_field_variation_data' );
function wc_proprice_add_custom_field_variation_data( $variations ) {
   $variations['prix_pro'] = '<div class="woocommerce_custom_field">Tarif professionnel: <span>' . get_post_meta( $variations[ 'variation_id' ], 'prix_pro', true ) . '</span></div>';
   return $variations;
}

/**
 * Sauvegarder champs personnalisé (client pro) du formulaire d'inscription Woocommerce
 */ 
add_action( 'woocommerce_created_customer', 'wc_save_registration_form_fields' );
function wc_save_registration_form_fields( $customer_id ) {
	if ( isset($_POST['role']) ) {
		if( $_POST['role'] == 'client professionnel' ){
			$user = new WP_User($customer_id);
			$user->set_role('client professionnel');
		}
	}
}

/* Afficher "À partir de" pour les produits variables */
add_filter( 'woocommerce_variable_sale_price_html', 'wpm_variation_price_format', 10, 2 );
add_filter( 'woocommerce_variable_price_html', 'wpm_variation_price_format', 10, 2 );

function wpm_variation_price_format( $price, $product ) {
	//On récupère le prix min et max du produit variable
	$min_price = $product->get_variation_price( 'min', true );
	$min_price_pro = get_min_variation_prices_pro($product);

	// Si les prix sont différents on affiche "À partir de ..."
	if ($min_price < $min_price_pro){
		$price = sprintf( __( 'A partir de %1$s', 'woocommerce' ), wc_price( $min_price ) );
		return $price;
	}else{
		$price = sprintf( __( 'A partir de %1$s', 'woocommerce' ), wc_price( $min_price_pro) );
		return $price;
	}
	
}

/**
 * Réécrire le prix du produit par le prix Professionnel si le client pro est log
 */
function wap_change_product_price_display( $price ){
	$post_id = get_the_ID();
	$current_product = wc_get_product( $post_id );

	if (is_cart())
		return $price;

	if (!is_professional_user(get_current_user_id()))
		return $price;

	$professional_price = get_professional_price($post_id);

	if(empty($professional_price)){
		return $price;
	}
	
	$html = do_action('wwp_before_pricing');
	$html .= '<span class="woocommerce-Price-amount amount">' . $professional_price;
	$html .= '<span class="woocommerce-Price-currencySymbol">€</span></span>';
	$html .= do_action('wwp_after_pricing');

	return $html;
}

add_filter( 'woocommerce_get_price_html', 'wap_change_product_price_display' );
add_filter( 'woocommerce_cart_item_price', 'wap_change_product_price_display' );

// Variable

add_filter('woocommerce_product_variation_get_regular_price', 'custom_price', 99, 2 );
add_filter('woocommerce_product_variation_get_price', 'custom_price' , 99, 2 );

function custom_price( $price, $product ) {
	global $wpdb;
	$discount_pro_table_name = $wpdb->prefix . 'discount_pro'; 
	$discount_data = [];
	$user_id = get_current_user_id();
	$percent_discount = 0;
	$user_id = get_current_user_id();

	if (!is_professional_user($user_id)){
		return $price;
	}

	$professional_price = get_post_meta($product->get_id(),'prix_pro',true);

	if(empty($professional_price)){
		return $price;
	}

	$discount_data = $wpdb->get_results(
		$wpdb->prepare("SELECT `category_id`, `discount_rate` FROM `{$discount_pro_table_name}` WHERE user_id = %d",
		$user_id
	));

	$product_cats = get_the_terms( $product->parent_id, 'product_cat' );
	//echo json_encode(get_the_terms( $product->parent_id, 'product_cat' ));
	if (sizeof($discount_data) != 0) {
		foreach ($discount_data as $discount_cat){
			foreach($product_cats as $cat_prod){
				if($cat_prod->parent == 0){
					if($discount_cat->{'category_id'} == $cat_prod->term_id){
						$percent_discount = $discount_cat->{'discount_rate'};
					}	
				}
			}
		}
	}

	$professional_price = $professional_price * (1 - $percent_discount / 100);
	$professional_price = round($professional_price, 2, PHP_ROUND_HALF_UP);

	// Delete product cached price  (if needed)
	wc_delete_product_transients($product->get_id());

	return $professional_price; 
}


/**
 * Checker si un client loggué est un client professionnel ou particulier
 */
function is_professional_user($user_id) {
	if(!empty($user_id)) {
			$user_info = get_userdata($user_id);
			$user_role = implode(', ', $user_info->roles);

			if($user_role == 'client professionnel')
					return true;
	}
	return false;
}

/**
 * Récupérer le prix professionnel 
 */
function get_professional_price($post_id) {
	global $wpdb;
	$discount_pro_table_name = $wpdb->prefix . 'discount_pro'; 
	$discount_data = [];
	$user_id = get_current_user_id();
	$percent_discount = 0;
	
	$professional_price = get_post_meta($post_id,'prix_pro',true);

	if(empty($professional_price)){
		return 0;
	}


	$discount_data = $wpdb->get_results(
		$wpdb->prepare("SELECT `category_id`, `discount_rate` FROM `{$discount_pro_table_name}` WHERE user_id = %d",
		$user_id
	));

	$product_cats = get_the_terms( $post_id, 'product_cat' );
	//echo json_encode(get_the_terms( $product->parent_id, 'product_cat' ));
	if (sizeof($discount_data) != 0) {
		foreach ($discount_data as $discount_cat){
			foreach($product_cats as $cat_prod){
				if($cat_prod->parent == 0){
					if($discount_cat->{'category_id'} == $cat_prod->term_id){
						$percent_discount = $discount_cat->{'discount_rate'};
					}	
				}
			}
		}
	}

	$professional_price = $professional_price * (1 - $percent_discount / 100);
	$professional_price = round($professional_price, 2, PHP_ROUND_HALF_UP);

	return $professional_price;
}

/**
 * Réécrire le prix du produit dans le panier si l'utilisateur est un client professionnel.
 */
function wap_override_product_price_cart( $_cart ){
	if(is_professional_user(get_current_user_id())) {
		// loop through the cart_contents
		foreach ( $_cart->cart_contents as $cart_item_key => $item ) {
			$pro_price = get_professional_price($item['product_id']);
			if(!empty($pro_price))
				$item['data']->set_price($pro_price);
		}
	}
}
add_action( 'woocommerce_before_calculate_totals', 'wap_override_product_price_cart',99 );


/**
 * Get an array of all sale and regular prices from all variations. This is used for example when displaying the price range at variable product level or seeing if the variable product is on sale.
 *
 * @param  bool $for_display If true, prices will be adapted for display based on the `woocommerce_tax_display_shop` setting (including or excluding taxes).
 * @return array Array of RAW prices, regular prices, and sale prices with keys set to variation ID.
 */
function get_min_variation_prices_pro($product) {
	$variation_ids = $product->get_visible_children();
	$prices = array();
	$first = 0;
	$lowest_price = 0;
	global $wpdb;
	$discount_pro_table_name = $wpdb->prefix . 'discount_pro'; 
	$discount_data = [];
	$user_id = get_current_user_id();
	$percent_discount = 0;

	foreach ( $variation_ids as $id_var  ) {
		$new_price = get_post_meta( $id_var, 'prix_pro', true );
		if ($first < 1 || ($lowest_price > $new_price)){
			$lowest_price = $new_price;
		}
		$first++;
	}

	$discount_data = $wpdb->get_results(
		$wpdb->prepare("SELECT `category_id`, `discount_rate` FROM `{$discount_pro_table_name}` WHERE user_id = %d",
		$user_id
	));

	$product_cats = get_the_terms( $post_id, 'product_cat' );
	
	if (sizeof($discount_data) != 0) {
		foreach ($discount_data as $discount_cat){
			foreach($product_cats as $cat_prod){
				if($cat_prod->parent == 0){
					if($discount_cat->{'category_id'} == $cat_prod->term_id){
						$percent_discount = $discount_cat->{'discount_rate'};
					}	
				}
			}
		}
	}

	$lowest_price = $lowest_price * (1 - $percent_discount / 100);
	$lowest_price = round($lowest_price, 2, PHP_ROUND_HALF_UP);

	return $lowest_price;
}


/**
 * Remove Locations from category widget
 */

add_filter( 'woocommerce_product_categories_widget_args', 'widget_arguments' );
add_filter('widget_categories_args', 'widget_arguments');
function widget_arguments( $args ) {

$args['exclude'] = '47';

return $args;
}

remove_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30 );
add_action( 'woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 40 );
add_action( 'woocommerce_before_shop_loop', 'filter_number_products', 30 );

if ( ! function_exists( 'filter_number_products' ) ) {
	function filter_number_products() {

		global $wp_query;

		$total = $wp_query->found_posts;
		$paged = max( 1, $wp_query->get( 'paged' ) );
		$limit = 9;

		$first = '';
		$second = '';
		$last = '';

		if ( isset( $_GET['show_products'] ) ) {
			if ( $_GET[ 'show_products' ] == 'all' ) {
				$last = 'active';
			} elseif ( $_GET[ 'show_products' ] == $limit * 2 ) {
				$second = 'active';
			} else {
				$first = 'active';
			}
		} else {
			$first = 'active';
		}

		$page_filter = '<div class="products-page-filter" method="get">';
		$page_filter .= '<span>' .  esc_html__('Produits par page : ') . '</span>';

		if ( $total > $limit ) {
			$page_filter .= '<a class="' . $first . '" href="' . esc_url( add_query_arg( 'show_products', $limit ) ) . '">' . $limit . '</a>';
		}
		if ( $total > $limit * 2 && $paged * $limit * 2 < $total ) {
			$page_filter .= '<a class="' . $second . '" href="' . esc_url( add_query_arg( 'show_products', $limit * 2 ) ) . '">' . $limit * 2 . '</a>';
		}
		if ( $total > $limit ) {
			$page_filter .= '<a class="' . $last . '" href="' . esc_url( add_query_arg( 'show_products', 'all' ) ) . '">' . esc_html__( 'All', 'lapin' ) . '</a>';
		}

		$page_filter .= '</div>';

		if ( $total > $limit ) {

			echo apply_filters( 'lapin_filter_number_products_filter', $page_filter );

		}
	}
}


if ( isset( $_GET['show_products'] ) ) {
	if ( $_GET[ 'show_products' ] == 'all' ) {
		add_filter( 'loop_shop_per_page', create_function( '$cols', 'return -1;' ) );
	} else {
		add_filter( 'loop_shop_per_page', create_function( '$cols', 'return '. $_GET[ 'show_products' ] . ';' ) );
	}
} else {
	$limit = 9;
	add_filter( 'loop_shop_per_page', create_function( '$cols', 'return '. $limit . ';' ) );
}

remove_action( 'woocommerce_after_single_product_summary', 'woocommerce_output_related_products', 20 );

add_filter('woocommerce_product_data_store_cpt_get_products_query', 'sam_exclude_cat_query', 10, 2);

function sam_exclude_cat_query($query, $query_vars) {
    if (!empty($query_vars['exclude_category'])) {
        $query['tax_query'][] = array(
            'taxonomy' => 'product_cat',
            'field'    => 'slug',
            'terms'    => $query_vars['exclude_category'], // Use the value of previous block of code
            'operator' => 'NOT IN',
        );
    }
    return $query;
}