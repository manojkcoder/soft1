<?php

/**
 * The plugin bootstrap file
 *
 * This file is read by WordPress to generate the plugin information in the plugin
 * admin area. This file also includes all of the dependencies used by the plugin,
 * registers the activation and deactivation functions, and defines a function
 * that starts the plugin.
 *
 * @link              http://example.com
 * @since             1.0.0
 * @package           Plugin_Name
 *
 * @wordpress-plugin
 * Plugin Name:       OBS Woocommerce Custom Soft1
 * Plugin URI:        http://obs.gr/OBSWoocommerceCustomSoft1/
 * Description:       This is a short description of what the plugin does. It's displayed in the WordPress admin area.
 * Version:           1.0.0
 * Author:            OBS
 * Author URI:        http://obs.gr/
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       plugin-name
 * Domain Path:       /languages
 */

use OBSWoocommerceCustomSoft1 as GlobalOBSWoocommerceCustomSoft1;
use OBSWoocommerceCustomSoft1Helpers as GlobalOBSWoocommerceCustomSoft1Helpers;

// If this file is called directly, abort.
if (!defined('WPINC')) {
	die;
}

/**
 * Currently plugin version.
 * Start at version 1.0.0 and use SemVer - https://semver.org
 * Rename this for your plugin and update it as you release new versions.
 */
define('PLUGIN_NAME_VERSION', '1.0.0');

require_once(__DIR__ . '/helpers.php');

class OBSWoocommerceCustomSoft1
{

	private $obs_woocommerce_custom_soft1_options;
	private $terms;
	private $helpers;

	public function __construct()
	{
		add_action('admin_menu', array($this, 'obs_woocommerce_custom_soft1_add_plugin_page'));
		add_action('admin_init', array($this, 'obs_woocommerce_custom_soft1_page_init'));
		//add_action('obs_soft1_import_products_event', array($this, 'insert_products'), 10, 1);
		add_filter('cron_schedules',  array('OBSWoocommerceCustomSoft1', 'custom_cron_schedules'));
		register_activation_hook(__FILE__, array($this, 'activate_obs_woocommerce_custom_soft1'));
		register_deactivation_hook(__FILE__, array($this, 'deactivate_obs_woocommerce_custom_soft1'));

		add_action('rest_api_init', function () {
			register_rest_route('obs_woocommerce_custom_soft1', '/update_stock', array(
				'methods' => 'POST',
				'callback' => array($this, 'obs_woocommerce_custom_soft1_get_products_stock'),
			));
		});
		add_filter('woocommerce_ajax_variation_threshold', array($this, '_ajax_threshold'));

		if (isset($_POST['test_connection'])) {
			$result = $this->perform_login();
			if ($result) {
				add_action('admin_notices', array($this, 'obs_woocommerce_custom_soft1_login_success'));
			} else {
				add_action('admin_notices', array($this, 'obs_woocommerce_custom_soft1_login_failure'));
			}
		}

		add_action('woocommerce_thankyou', array($this, 'obs_woocommerce_custom_soft1_send_order_to_erp'), 10, 1);
		if (isset($_GET['testing_capital'])) {
			ini_set('max_execution_time', 0);
			add_action('init', function () {
				$this->obs_woocommerce_custom_soft1_send_order_to_erp(64487);
				//$product = wc_get_product(62596);
				//die($product->is_on_sale() ? $product->get_sale_price() : $product->get_regular_price());
			});

			//add_action( 'init', function(){ $this->obs_woocommerce_custom_soft1_get_products();die(); });
			/*
			add_action( 'init', function(){ 
			    $posts = get_posts([
			        'fields' => 'ids',
    				'post_type' => 'product_variation',
    				'numberposts' => -1,
    				'meta_query' => array(
                        array(
                         'key' => 'MTRL_MTRSUBSTITUTE_CODE',
                         'compare' => 'EXISTS'
                        ),
                    ),
    			]);
    			
    			foreach($posts as $post){
    			    $mtrl = get_post_meta($post, 'MTRL_MTRSUBSTITUTE_CODE', TRUE);
    			    echo $mtrl.'<br>';
    			    update_post_meta($post, 'MTRL_MTRSUBSTITUTE_CODE', str_pad($mtrl, 13, '0', STR_PAD_LEFT));
    			}
			});
			*/
		}
		if (isset($_GET['sync_failed_orders'])) {
			ini_set('max_execution_time', 0);
			add_action('init', function () {
				$this->obs_woocommerce_custom_soft1_sync_failed_orders();
				die();
			});
		}
		if (isset($_GET['obs_softone_resync'])) {
			ini_set('max_execution_time', 0);
			add_action('init', function () {
				$this->obs_woocommerce_custom_soft1_send_order_to_erp($_GET['obs_softone_resync']);
				die();
			});
		}
		if (isset($_GET['softone_cron'])) {
			ini_set('max_execution_time', 0);
			add_action('init', function () {
				$this->obs_woocommerce_custom_soft1_get_products();
				die();
			});
		}
		if (isset($_GET['obs_test_import'])) {
			ini_set('max_execution_time', 0);
			add_action('init', function () {
				$this->obs_test_import();
				die();
			});
		}
		if (isset($_GET['softone_cron_import_stock'])) {
			ini_set('max_execution_time', 0);
			add_action('init', function () {
				$this->obs_woocommerce_custom_soft1_get_products_stock();
				die();
			});
		}
		if (isset($_REQUEST['export_stock_csv'])) {
			ini_set('max_execution_time', 0);
			add_action('init', function () {
				$this->obs_woocommerce_custom_soft1_get_products_stock();
				die();
			});
		}
		if (isset($_REQUEST['export_csv'])) {
			add_action('init', function () {
				$this->obs_woocommerce_custom_soft1_get_products();
				die();
			});
		}
		if (isset($_REQUEST['export_stock_raw_csv'])) {
			add_action('init', function () {
				$this->obs_woocommerce_custom_soft1_get_products_stock();
				die();
			});
		}
		add_action('obs_woocommerce_custom_soft1_get_products_stock', [$this, 'obs_woocommerce_custom_soft1_get_products_stock'], 10, 0);
		add_action('obs_woocommerce_custom_soft1_get_products', [$this, 'obs_woocommerce_custom_soft1_get_products'], 10, 0);
		add_action('woocommerce_admin_order_data_after_shipping_address', [$this, 'show_softone_data']);
	}

	function show_softone_data()
	{
		global $post;
		$obs_custom_soft1_order_id = get_post_meta($post->ID, 'obs_custom_soft1_order_id', TRUE);
		echo '<strong>Softone:</strong><br>';
		if ($obs_custom_soft1_order_id) {
			echo 'Order ID: ' . $obs_custom_soft1_order_id;
		} else {
			echo '<a href="?obs_softone_resync=' . $post->ID . '" target="_blank">Re-Sync</a>';
		}
	}

	function _ajax_threshold()
	{
		return 100;
	}

	public function jsonToCSV($data)
	{
		$fp = fopen('php://memory', 'w');
		fputs($fp, $bom = (chr(0xEF) . chr(0xBB) . chr(0xBF)));
		$header = false;
		foreach ($data as $row) {
			if (!$header) {
				$header = array_keys($row);
				fputcsv($fp, $header);
				$header = array_flip($header);
			}
			fputcsv($fp, $row);
		}
		fseek($fp, 0);
		header('Content-Type: application/csv');
		header('Content-Disposition: attachment; filename="export.csv";');
		fpassthru($fp);
		die();
	}

	public function obs_woocommerce_custom_soft1_get_products_stock()
	{
		$client_id = $this->get_client_id();

		$stocks = [];
		$raw_data = [];

		$obs_woocommerce_custom_soft1_options = get_option('obs_woocommerce_custom_soft1_option_name');
		// print_r($obs_woocommerce_custom_soft1_options);

   

		$data = [
			"service" => "SqlData",
			"clientID" => $client_id,
			"appId" => $obs_woocommerce_custom_soft1_options["appId"],
			"SqlName" => "MTRL_BALANCE_SHISHA",
			"param1"  => "2022"
		];
		// print_r($data);
		$response_data = $this->get_api_response($data)['body'];
		$products = json_decode(iconv("Windows-1253", "UTF-8", $response_data), TRUE);
		
		if (count($products)) {
			$data = [];

			foreach ($products as $product) {
				$product_id = OBSWoocommerceCustomSoft1Helpers::find_product_id($key, '_sku');
				if ($product_id) {
					OBSWoocommerceCustomSoft1Helpers::update_stock($product_id, $qty);
				}
			}
			//print_r($stocks);
			if (isset($_REQUEST['export_stock_csv'])) {
				$this->jsonToCSV($data);
				die();
			}
			if (isset($_REQUEST['export_stock_raw_csv'])) {
				$this->jsonToCSV($raw_data);
				die();
			}
		}
	}

	
	public function slugify($string)
	{
		$slug = preg_replace("/&#?[a-z0-9]{2,8};/i", "", $string);
		$slug = str_replace('  ', ' ', $slug);
		$slug = strtolower(str_replace(' ', '-', $slug));
		return $slug;
	}

	



	public function obs_woocommerce_custom_soft1_login_success()
	{
?>
		<div class="notice notice-success is-dismissible">
			<p><?php _e('Login Successful, Credentials are valid!', 'obs_woocommerce_custom_soft1'); ?></p>
		</div>
	<?php
	}

	public function obs_woocommerce_custom_soft1_login_failure()
	{
	?>
		<div class="notice notice-error is-dismissible">
			<p><?php _e('Login Error, Credentials are invalid!', 'obs_woocommerce_custom_soft1'); ?></p>
		</div>
	<?php
	}

	public function activate_obs_woocommerce_custom_soft1()
	{
		if (!wp_next_scheduled('obs_woocommerce_custom_soft1_get_products_stock')) {
			wp_schedule_event(time(), 'every_3_hours', 'obs_woocommerce_custom_soft1_get_products_stock', []);
		}
	}

	static function custom_cron_schedules($schedules)
	{
		if (!isset($schedules['every_3_hours'])) {
			$schedules['every_3_hours'] = array(
				'interval' => 3 * 60 * 60,
				'display' => __('Once every 3 Hours')
			);
		}
		return $schedules;
	}

	public function deactivate_obs_woocommerce_custom_soft1()
	{
		wp_clear_scheduled_hook('obs_woocommerce_custom_soft1_get_products');
		wp_clear_scheduled_hook('obs_woocommerce_custom_soft1_get_products_stock');
	}



	public function obs_woocommerce_custom_soft1_add_plugin_page()
	{
		add_options_page(
			'OBS Woocommerce Custom Soft1', // page_title
			'OBS Woocommerce Custom Soft1', // menu_title
			'manage_options', // capability
			'obs-woocommerce-custom-soft1', // menu_slug
			array($this, 'obs_woocommerce_custom_soft1_create_admin_page') // function
		);
	}

	public function obs_woocommerce_custom_soft1_create_admin_page()
	{
		$this->obs_woocommerce_custom_soft1_options = get_option('obs_woocommerce_custom_soft1_option_name'); ?>

		<div class="wrap">
			<h2>OBS Woocommerce Custom Soft1</h2>
			<p></p>
			<?php settings_errors(); ?>

			<form method="post" action="options.php">
				<?php
				settings_fields('obs_woocommerce_custom_soft1_option_group');
				do_settings_sections('obs-woocommerce-custom-soft1-admin');
				submit_button('Save');
				?>
			</form>

			<form method="post">
				<?php
				if (is_array(get_option('obs_woocommerce_custom_soft1_option_name')) && array_filter(get_option('obs_woocommerce_custom_soft1_option_name')) !== []) {
					submit_button('TEST CONNECTION', 'secondary', 'test_connection', false);
					submit_button('EXPORT PRODUCT STOCK CSV', 'secondary', 'export_stock_raw_csv', false);
				}
				?>
			</form>
		</div>
<?php }

	public function obs_woocommerce_custom_soft1_page_init()
	{
		/*
	    foreach([] as $id){
    		update_post_meta($id, '_stock', 0);
    		update_post_meta( $id, '_stock_status', wc_clean( 'outofstock' ) );
    		wp_set_post_terms( $id, 'outofstock', 'product_visibility', true );
	    }
	    */
		register_setting(
			'obs_woocommerce_custom_soft1_option_group', // option_group
			'obs_woocommerce_custom_soft1_option_name', // option_name
			array($this, 'obs_woocommerce_custom_soft1_sanitize') // sanitize_callback
		);

		add_settings_section(
			'obs_woocommerce_custom_soft1_setting_section', // id
			'Settings', // title
			array($this, 'obs_woocommerce_custom_soft1_section_info'), // callback
			'obs-woocommerce-custom-soft1-admin' // page
		);

		add_settings_field(
			'api_url', // id
			'API URL', // title
			array($this, 'api_url_callback'), // callback
			'obs-woocommerce-custom-soft1-admin', // page
			'obs_woocommerce_custom_soft1_setting_section' // section
		);

		add_settings_field(
			'username', // id
			'Username', // title
			array($this, 'username_callback'), // callback
			'obs-woocommerce-custom-soft1-admin', // page
			'obs_woocommerce_custom_soft1_setting_section' // section
		);

		add_settings_field(
			'password', // id
			'Password', // title
			array($this, 'password_callback'), // callback
			'obs-woocommerce-custom-soft1-admin', // page
			'obs_woocommerce_custom_soft1_setting_section' // section
		);


		add_settings_field(
			'appId', // id
			'App Id', // title
			array($this, 'appId_callback'), // callback
			'obs-woocommerce-custom-soft1-admin', // page
			'obs_woocommerce_custom_soft1_setting_section' // section
		);


		add_settings_field(
			'clientId', // id
			'Client Id', // title
			array($this, 'clientId_callback'), // callback
			'obs-woocommerce-custom-soft1-admin', // page
			'obs_woocommerce_custom_soft1_setting_section' // section
		);

		add_settings_field(
			'cod_charges', // id
			'COD Charges', // title
			array($this, 'cod_charges_callback'), // callback
			'obs-woocommerce-custom-soft1-admin', // page
			'obs_woocommerce_custom_soft1_setting_section' // section
		);

		add_settings_field(
			'payment_method_mapping', // id
			'Payment Method Mapping', // title
			array($this, 'payment_method_mapping_callback'), // callback
			'obs-woocommerce-custom-soft1-admin', // page
			'obs_woocommerce_custom_soft1_setting_section' // section
		);

		add_settings_field(
			'shipping_method_mapping', // id
			'Shipping Method Mapping', // title
			array($this, 'shipping_method_mapping_callback'), // callback
			'obs-woocommerce-custom-soft1-admin', // page
			'obs_woocommerce_custom_soft1_setting_section' // section
		);
	}

	public function obs_woocommerce_custom_soft1_sanitize($input)
	{
		$sanitary_values = array();

		if (isset($input['api_url'])) {
			$sanitary_values['api_url'] = sanitize_text_field($input['api_url']);
		}

		if (isset($input['username'])) {
			$sanitary_values['username'] = sanitize_text_field($input['username']);
		}

		if (isset($input['appId'])) {
			$sanitary_values['appId'] = sanitize_text_field($input['appId']);
		}

		if (isset($input['clientId'])) {
			$sanitary_values['clientId'] = sanitize_text_field($input['clientId']);
		}

		if (isset($input['cod_charges'])) {
			$sanitary_values['cod_charges'] = sanitize_text_field($input['cod_charges']);
		}

		if (isset($input['shipping_method_mapping'])) {
			$sanitary_values['shipping_method_mapping'] = sanitize_text_field(json_encode($input['shipping_method_mapping']));
		}

		if (isset($input['payment_method_mapping'])) {
			$sanitary_values['payment_method_mapping'] = sanitize_text_field(json_encode($input['payment_method_mapping']));
		}

		if (isset($input['password'])) {
			$sanitary_values['password'] = sanitize_text_field($input['password']);
		}

		return $sanitary_values;
	}

	public function obs_woocommerce_custom_soft1_section_info()
	{
	}

	public function payment_method_mapping_callback()
	{
		$saved_value = isset($this->obs_woocommerce_custom_soft1_options['payment_method_mapping']) ? json_decode($this->obs_woocommerce_custom_soft1_options['payment_method_mapping'], true) : [];
		$gateways = WC()->payment_gateways->get_available_payment_gateways();
		$enabled_gateways = [];

		if ($gateways) {
			echo '<table>';
			foreach ($gateways as $gateway) {
				if ($gateway->enabled == 'yes') {
					printf(
						'<tr><td>%s:</td><td><input class="regular-text" type="text" name="obs_woocommerce_custom_soft1_option_name[payment_method_mapping][%s]" id="payment_method_mapping" value="%s"></td></tr>',
						$gateway->title,
						$gateway->id,
						isset($saved_value[$gateway->id]) ? $saved_value[$gateway->id] : ''
					);
				}
			}
			echo '</table>';
		}
	}

	public function shipping_method_mapping_callback()
	{
		$saved_value = isset($this->obs_woocommerce_custom_soft1_options['shipping_method_mapping']) ? json_decode($this->obs_woocommerce_custom_soft1_options['shipping_method_mapping'], true) : [];
		$gateways = WC()->shipping()->get_shipping_methods();
		$enabled_gateways = [];

		if ($gateways) {
			echo '<table>';
			foreach ($gateways as $gateway) {
				if ($gateway->enabled == 'yes') {
					printf(
						'<tr><td>%s:</td><td><input class="regular-text" type="text" name="obs_woocommerce_custom_soft1_option_name[shipping_method_mapping][%s]" id="shipping_method_mapping" value="%s"></td></tr>',
						!empty($gateway->title) ? $gateway->title : $gateway->id,
						$gateway->id,
						isset($saved_value[$gateway->id]) ? $saved_value[$gateway->id] : ''
					);
				}
			}
			echo '</table>';
		}
	}

	public function api_url_callback()
	{
		printf(
			'<input class="regular-text" type="text" name="obs_woocommerce_custom_soft1_option_name[api_url]" id="api_url" value="%s">',
			isset($this->obs_woocommerce_custom_soft1_options['api_url']) ? esc_attr($this->obs_woocommerce_custom_soft1_options['api_url']) : ''
		);
	}

	public function username_callback()
	{
		printf(
			'<input class="regular-text" type="text" name="obs_woocommerce_custom_soft1_option_name[username]" id="username" value="%s">',
			isset($this->obs_woocommerce_custom_soft1_options['username']) ? esc_attr($this->obs_woocommerce_custom_soft1_options['username']) : ''
		);
	}

	public function appId_callback()
	{
		printf(
			'<input class="regular-text" type="text" name="obs_woocommerce_custom_soft1_option_name[appId]" id="appId" value="%s">',
			isset($this->obs_woocommerce_custom_soft1_options['appId']) ? esc_attr($this->obs_woocommerce_custom_soft1_options['appId']) : ''
		);
	}

	public function clientId_callback()
	{
		printf(
			'<input class="regular-text" type="text" name="obs_woocommerce_custom_soft1_option_name[clientId]" id="clientId" value="%s">',
			isset($this->obs_woocommerce_custom_soft1_options['clientId']) ? esc_attr($this->obs_woocommerce_custom_soft1_options['clientId']) : ''
		);
	}

	public function cod_charges_callback()
	{
		printf(
			'<input class="regular-text" type="text" name="obs_woocommerce_custom_soft1_option_name[cod_charges]" id="cod_charges" value="%s">',
			isset($this->obs_woocommerce_custom_soft1_options['cod_charges']) ? esc_attr($this->obs_woocommerce_custom_soft1_options['cod_charges']) : ''
		);
	}

	public function password_callback()
	{
		printf(
			'<input class="regular-text" type="text" name="obs_woocommerce_custom_soft1_option_name[password]" id="password" value="%s">',
			isset($this->obs_woocommerce_custom_soft1_options['password']) ? esc_attr($this->obs_woocommerce_custom_soft1_options['password']) : ''
		);
	}


	protected function get_api_response($params)
	{

		$obs_woocommerce_custom_soft1_options = get_option('obs_woocommerce_custom_soft1_option_name'); // Array of All Options
		if (isset($obs_woocommerce_custom_soft1_options['api_url']) && !empty($obs_woocommerce_custom_soft1_options['api_url'])) {
			$endpoint = $obs_woocommerce_custom_soft1_options['api_url'];

			$body = wp_json_encode($params);

			$options = [
				'body'        => $body,
				'headers'     => [
					'Content-Type' => 'application/json',
				],
				'timeout'     => 60,
				'redirection' => 5,
				'blocking'    => true,
				'httpversion' => '1.0',
				'sslverify'   => false,
				'data_format' => 'body',
			];

			$response = wp_remote_post($endpoint, $options);
			if (!is_wp_error($response)) {
				return $response;
			}
		}
		return null;
	}

	public function perform_login()
	{
		$obs_woocommerce_custom_soft1_options = get_option('obs_woocommerce_custom_soft1_option_name'); // Array of All Options
		$response = $this->get_api_response(array_merge(['service' => 'login'], $obs_woocommerce_custom_soft1_options))['body'];
		if ($response) {
			// echo $response;
			$resultsArray = json_decode(iconv("Windows-1253", "UTF-8", $response));
			if ($resultsArray->success) {
				$client_id = $resultsArray->clientID;
				foreach ($resultsArray->objs as $res) {
					$company = $res->COMPANY;
					$companyname = $res->COMPANYNAME;
					$branch = $res->BRANCH;
					$branchname = $res->BRANCHNAME;
					$userid = $res->USERID;
					$module = $res->MODULE;
					$refid = $res->REFID;
					if ($company == 1) {
						break;
					}
				}
				$auth = [
					'service' => "authenticate",
					'clientID' =>  $client_id,
					'company' => $company,
					'branch' => $branch,
					'module' => $module,
					'refid' => $refid,
				];
				$response = $this->get_api_response($auth)['body'];
				$resultsArray = json_decode(iconv("Windows-1253", "UTF-8", $response));
				if ($resultsArray->success) {
					update_option('obs_woocommerce_custom_soft1_client_id', $resultsArray->clientID);
					return true;
				}
			}
		}
		return false;
	}

	protected function get_client_id()
	{
		$obs_woocommerce_custom_soft1_client_id = get_option('obs_woocommerce_custom_soft1_client_id');
		if ($obs_woocommerce_custom_soft1_client_id == '') {
			return null;
		}
		return $obs_woocommerce_custom_soft1_client_id;
	}
}

$obs_woocommerce_custom_soft1 = new OBSWoocommerceCustomSoft1();

/* 
 * Retrieve this value with:
 * $obs_woocommerce_custom_soft1_options = get_option( 'obs_woocommerce_custom_soft1_option_name' ); // Array of All Options
 * $company = $obs_woocommerce_custom_soft1_options['company']; // Company
 * $fiscalyear = $obs_woocommerce_custom_soft1_options['fiscalyear']; // Fiscal Year
 * $branch = $obs_woocommerce_custom_soft1_options['branch']; // Branch
 * $username = $obs_woocommerce_custom_soft1_options['username']; // Username
 * $password = $obs_woocommerce_custom_soft1_options['password']; // Password
 */
