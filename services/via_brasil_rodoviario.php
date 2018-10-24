<?php 

use Controllers\PackageController;
use Controllers\CotationController;
use Controllers\ProductsController;
use Controllers\TimeController;
use Controllers\MoneyController;

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    add_action( 'woocommerce_shipping_init', 'via_brasil_rodoviario_shipping_method_init' );
	function via_brasil_rodoviario_shipping_method_init() {
		if ( ! class_exists( 'WC_via_brasil_rodoviario_Shipping_Method' ) ) {

			class WC_Via_Brasil_Rodoviario_Shipping_Method extends WC_Shipping_Method {

                public $code = '9';
				/**
				 * Constructor for your shipping class
				 *
				 * @access public
				 * @return void
				 */
				public function __construct($instance_id = 0) {
					$this->id                 = "via_brasil_rodoviario"; 
                    $this->instance_id = absint( $instance_id );
                    $this->method_title       = "Via Brasil rodoviario (Melhor envio)"; 
					$this->method_description = 'Serviço Via Brasil rodoviario';
					$this->enabled            = "yes"; 
					$this->title              = isset($this->settings['title']) ? $this->settings['title'] : 'Melhor Envio Via Brasil Rodoviario';
                    $this->supports = array(
                        'shipping-zones',
                        'instance-settings',
                    );
					$this->init_form_fields();
				}
				
				/**
				 * Init your settings
				 *
				 * @access public
				 * @return void
				 */
				function init() {
					$this->init_form_fields(); 
					$this->init_settings(); 
					add_action('woocommerce_update_options_shipping_' . $this->id, array($this, 'process_admin_options'));
				}

				/**
				 * calculate_shipping function.
				 *
				 * @access public
				 * @param mixed $package
				 * @return void
				 */
				public function calculate_shipping( $package = []) {
					
					global $woocommerce;
					$to = str_replace('-', '', $package['destination']['postcode']);

					$prod = new ProductsController();
					$products = $prod->getProductsCart();
					
					$cotation = new CotationController();					
					$result = $cotation->makeCotationproducts($products, [$this->code], $to);

					if ($result = $cotation->makeCotationproducts($products, [$this->code], $to)) {

						if (isset($result->name) && isset($result->price)) {
							$rate = [
								'id' => 'melhorenvio_via_brasil_rodoviario',
								'label' => $result->name . (new timeController)->setLabel($result->delivery_range),
								'cost' => (new MoneyController())->setprice($result->price),
								'calc_tax' => 'per_item',
								'meta_data' => [
									'delivery_time' => $result->delivery_time,
									'company' => 'Via Brasil'
								]
							]; 
							$this->add_rate($rate);
						}
					}
                }
                
                /**
				 * Initialise Gateway Settings Form Fields
				 */
				function init_form_fields() {

					$this->form_fields = [
						'title' => [
							'title' => 'Titulo',
							'type' => 'text',
							'default' => 'Via Brasil Rodoviario'
						],
						'enabled' => [
							'title' => 'Ativar',
							'type' => 'checkbox',
							'default' => 'yes'
						],
					];
				}   
			}
		}
	}
	
	function add_via_brasil_rodoviario_shipping_method( $methods ) {
		$methods['via_brasil_rodoviario'] = 'WC_via_brasil_rodoviario_Shipping_Method';
		return $methods;
	}
	add_filter( 'woocommerce_shipping_methods', 'add_via_brasil_rodoviario_shipping_method' );
}
