<?php
if (!class_exists('OBSWoocommerceCustomSoft1Helpers')) {
	class OBSWoocommerceCustomSoft1Helpers
	{

		public static function find_product_id($value, $key = '_sku')
		{
			if ($key == '_sku') {
				return wc_get_product_id_by_sku($value);
			} else {
				$post_ids = get_posts([
					'meta_key'   		=> $key,
					'meta_value' 		=> $value,
					'fields'     		=> 'ids',
					'post_type'			=> 'product',
					'posts_per_page'  	=> -1
				]);
				if (count($post_ids)) {
					return array_shift($post_ids);
				}
				$post_ids = get_posts([
					'meta_key'   		=> $key,
					'meta_value' 		=> $value,
					'fields'     		=> 'ids',
					'post_type'			=> 'product_variation',
					'posts_per_page'  	=> -1
				]);
				if (count($post_ids)) {
					return array_shift($post_ids);
				}
			}
			return null;
		}


		public static function update_stock($product_id, $qty)
		{
			$product = wc_get_product($product_id);
			$product->set_stock_quantity(wc_stock_amount($qty));
			$product->set_stock_status(intval($qty) > 0 ? 'instock' : 'outofstock');
			$product->save();
		}


		public static function create_category($category, $parent_id = null)
		{

			if (empty($category)) {
				return null;
			}

			$category = esc_html($category);
			$args = [];
			if ($parent_id) {
				$args['parent'] = $parent_id;
			} else {
				$args['parent'] = 0;
			}

			$term = term_exists($category, 'product_cat', $args['parent']);

			if (0 !== $term && null !== $term) {
				$term_id = $term['term_id'];
			} else {
				$term = wp_insert_term($category, 'product_cat', $args);
				if (!is_wp_error($term)) {
					$term_id = $term['term_id'];
				} else {
					$term_id = null;
				}
			}
			return $term_id;
		}

		public static function find_child($parent_id, $value, $tax, $create_if_not_exists = false)
		{
			$sub_categories = get_categories(
				array('parent' => $parent_id, 'taxonomy' => $tax)
			);
			foreach ($sub_categories as $sub_category) {
				if ($sub_category->name == $value) {
					return $sub_category->term_id;
				}
			}
			return static::create_category($value, $parent_id);
		}

		public static function find_term_by($key, $value, $tax, $return_format, $create_if_not_exists = false)
		{
			$term = get_term_by($key, $value, $tax, $return_format);
			if ($term) {
				return $term;
			}
			if ($create_if_not_exists) {
				return static::create_category($value);
			}
		}

		public static function create_term($term, $taxonomy)
		{
			$term_exists = term_exists($term, wc_attribute_taxonomy_name($taxonomy));
			if (!$term_exists) {
				$term = wp_insert_term($term, wc_attribute_taxonomy_name($taxonomy), [
					'description' => $term,
					'slug' => sanitize_title($term)
				]);
				if (is_wp_error($term)) {
					$term = null;
				}
			} else {
				$term = $term_exists;
			}
			return $term;
		}

		public static function find_tax_class($rate)
		{
			$all_tax_rates = [];
			$tax_classes = WC_Tax::get_tax_classes(); // Retrieve all tax classes.
			if (!in_array('', $tax_classes)) { // Make sure "Standard rate" (empty class name) is present.
				array_unshift($tax_classes, '');
			}
			foreach ($tax_classes as $tax_class) { // For each tax class, get all rates.
				$taxes = WC_Tax::get_rates_for_tax_class($tax_class);
				$all_tax_rates = array_merge($all_tax_rates, $taxes);
			}
			foreach ($all_tax_rates as $tax_rate) {
				if (floatval($tax_rate->tax_rate) == floatval(str_replace(",", '.', $rate)))
					return $tax_rate->tax_rate_class;
			}
			return '';
		}

		public static function create_attribute($attribute_name)
		{
			$attribute_name = substr($attribute_name, 0, 28);
			$taxonomy_name = wc_attribute_taxonomy_name($attribute_name);
			if (!taxonomy_exists(wc_attribute_taxonomy_name($taxonomy_name))) {
				$data = [
					'name'         => $attribute_name,
					'slug'         => wc_sanitize_taxonomy_name($attribute_name),
					'type'         => 'select',
					'order_by'     => 'menu_order',
					'has_archives' => true
				];
				wc_create_attribute($data);

				register_taxonomy(
					$taxonomy_name,
					apply_filters('woocommerce_taxonomy_objects_' . $taxonomy_name, array('product')),
					apply_filters('woocommerce_taxonomy_args_' . $taxonomy_name, array(
						'labels'       => array(
							'name' => $attribute_name,
						),
						'hierarchical' => true,
						'show_ui'      => false,
						'query_var'    => true,
						'rewrite'      => false,
					))
				);
				self::delete_all_transients();
			}
		}

		public static function set_attribute($args)
		{
			global $wpdb;

			// create attribute
			static::create_attribute($args['name']);

			// create_term
			$options = [];
			if (is_array($args['options'])) {
				foreach ($args['options'] as $term_name) {
					static::create_term($term_name, $args['name']);
					array_push($options, $term_name);
				}
			} else {
				static::create_term($args['options'], $args['name']);
				array_push($options, $args['options']);
			}

			$attribute = new WC_Product_Attribute();
			$attribute->set_id(wc_attribute_taxonomy_id_by_name($args['name']) ? wc_attribute_taxonomy_id_by_name($args['name']) : 1);
			$attribute->set_name(wc_attribute_taxonomy_name($args['name']));
			$attribute->set_options($options);
			$attribute->set_position(isset($args['position']) ? $args['position'] : 1);
			$attribute->set_visible(isset($args['visibility']) ? $args['visibility'] : 1);
			$attribute->set_variation(isset($args['variation']) ? $args['variation'] : 0);

			return $attribute;
		}

		public static function delete_all_transients()
		{
			global $wpdb;
			$sql = 'DELETE FROM ' . $wpdb->options . ' WHERE option_name LIKE "_transient_%"';
			$wpdb->query($sql);
		}

		public static function have_orders_for_a_product($product_id)
		{
			global $wpdb;

			// Define HERE the orders status to include in  <==  <==  <==  <==  <==  <==  <==
			$orders_statuses = "'wc-completed', 'wc-processing', 'wc-on-hold'";

			# Get All defined statuses Orders IDs for a defined product ID (or variation ID)
			$count = $wpdb->get_var(
				"
            SELECT count(DISTINCT woi.order_id)
            FROM {$wpdb->prefix}woocommerce_order_itemmeta as woim, 
                    {$wpdb->prefix}woocommerce_order_items as woi, 
                    {$wpdb->prefix}posts as p
            WHERE  woi.order_item_id = woim.order_item_id
            AND woi.order_id = p.ID
            AND p.post_status IN ( $orders_statuses )
            AND woim.meta_key IN ( '_product_id', '_variation_id' )
            AND woim.meta_value LIKE '$product_id'
            ORDER BY woi.order_item_id DESC"
			);
			return intval($count) > 0;
		}

		public static function array_filter_recursive($array, $callback = null, $remove_empty_arrays = false)
		{
			foreach ($array as $key => &$value) { // mind the reference
				if (is_array($value)) {
					$value = static::array_filter_recursive($value, $callback, $remove_empty_arrays);
					if ($remove_empty_arrays && !(bool) $value) {
						unset($array[$key]);
					}
				} else {
					if (!is_null($callback) && !$callback($value, $key)) {
						unset($array[$key]);
					} elseif (!(bool) $value) {
						unset($array[$key]);
					}
				}
			}
			unset($value); // kill the reference
			return $array;
		}


		public static function delete_or_draft_product($product)
		{
			$product = is_numeric($product) ? wc_get_product($product) : $product;
			if ($product) {
				$have_orders = static::have_orders_for_a_product($product->get_id());
				if ($have_orders) {
					$product->set_status("draft");
					$product->save();
				} else {
					wp_delete_post($product->get_id());
				}
			}
		}

		public static function delete_all_product_media($post_id)
		{

			if (get_post_type($post_id) == "product") {
				$attachments = get_attached_media('', $post_id);

				foreach ($attachments as $attachment) {
					wp_delete_attachment($attachment->ID, 'true');
				}
			}
		}
	}
}
