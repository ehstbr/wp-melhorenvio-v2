<?php 

if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ) {

    add_action( 'woocommerce_shipping_init', 'pac_shipping_method_init' );
	function pac_shipping_method_init() {
		if ( ! class_exists( 'WC_Pac_Shipping_Method' ) ) {
			class WC_Pac_Shipping_Method extends WC_Shipping_Method {

                protected $code = '1';
				/**
				 * Constructor for your shipping class
				 *
				 * @access public
				 * @return void
				 */
				public function __construct($instance_id = 0) {
					$this->id                 = "pac"; 
                    $this->instance_id = absint( $instance_id );
                    $this->method_title       = "Correios Pac (Melhor envio)"; 
					$this->method_description = 'Serviço Pac';
					$this->enabled            = "yes"; 
					$this->title              = (get_option('woocommerce_pac_title_custom_shipping')) ? get_option('woocommerce_pac_title_custom_shipping') : "Correios Pac";
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
				}

				/**
				 * calculate_shipping function.
				 *
				 * @access public
				 * @param mixed $package
				 * @return void
				 */
				public function calculate_shipping( $package = []) {

					$ar = get_use_ar_pac();
					$mp = get_use_mp_pac();

                    $result = wpmelhorenviopackage_getPackageInternal($package, $this->code, $ar, $mp);

                    if ($result != null && $result->price > 0) {

                        $rate = array(
                            'id'       => "wpmelhorenvio_".$result->company->name."_".$result->name,
                            'label'    => $this->title . calculte_pac_delivery_time($result->delivery_range),
                            'cost'     => calcute_value_shipping_pac($result->price),
                            'calc_tax' => 'per_item',
							'meta_data' => [
								'delivery_time' => $result->delivery_time,
								'company' => $result->company->name
							]
                        );
                        $this->add_rate( $rate );
                    }
                    
                }
                
                /**
				 * Initialise Gateway Settings Form Fields
				 */
				function init_form_fields() {

					$this->form_fields = array(
						'custom_shipping' => array(
							'title' => 'shipping',
							'type' => 'hidden',
							'default' => 'pac'
						),
						'title_custom_shipping' => array(
							'title' => 'Título',
							'description' => 'Nome do serviço exibido para o cliente',
							'desc_tip'    => true,
							'type' => 'text',
							'default' => (get_option('woocommerce_pac_title_custom_shipping')) ? get_option('woocommerce_pac_title_custom_shipping') : "Pac"
						),
						'ee_custom_shipping' => array(
							'title'   => 'Tempo de entrega',
							'type'    => 'checkbox',
							'description' => 'Exibir o tempo estimado de entrega em dias úteis',
							'desc_tip'    => true,
							'label'   => 'Exibir estimativa de entrega',
							'default' => (get_option('woocommerce_pac_ee_custom_shipping')) ? get_option('woocommerce_pac_ee_custom_shipping') : "no"
						),
						// 'ar_custom_shipping' => array(
						// 	'title'   => 'Aviso de recebimento',
						// 	'type'    => 'checkbox',
						// 	'description' => 'Isso controla se deve adicionar o custo de serviço de aviso de recebimento',
						// 	'desc_tip'    => true,
						// 	'label'   => 'Ativar aviso de recebimento',
						// 	'default' => (get_option('woocommerce_pac_ar_custom_shipping')) ? get_option('woocommerce_pac_ar_custom_shipping') : "no"
						// ),
						// 'mp_custom_shipping' => array(
						// 	'title'   => 'Mão Propria',
						// 	'type'    => 'checkbox',
						// 	'description' => 'Isso controla se deve adicionar o custo de serviço de mão própria',
						// 	'desc_tip'    => true,
						// 	'label'   => 'Ativar mão Propria',
						// 	'default' => (get_option('woocommerce_pac_mp_custom_shipping')) ? get_option('woocommerce_pac_mp_custom_shipping') : "no"
						// ),
						// 'vd_custom_shipping' => array(
						// 	'title'   => 'Declarar valor para seguro',
						// 	'type'    => 'checkbox',
						// 	'description' => 'Isso controla se o preço da encomenda deve ser declarado para própositos de seguro',
						// 	'desc_tip'    => true,
						// 	'label'   => 'Ativar valor declarado',
						// 	'default' => (get_option('woocommerce_pac_vd_custom_shipping')) ? get_option('woocommerce_pac_vd_custom_shipping') : "no"
						// ),
						'days_extra_custom_shipping' => array(
							'title'   => 'Dias extras',
							'type'    => 'number',
							'description' => 'Adiciona dias na estimativa na entrega',
							'desc_tip'    => true,
							'label'   => 'Dias a mais a serem adicionados na exibição do Prazo de Entrega.',
							'default' => (get_option('woocommerce_pac_days_extra_custom_shipping')) ? get_option('woocommerce_pac_days_extra_custom_shipping') : 0
						),
						'pl_custom_shipping' => array(
							'title'   => 'Taxa de manuseio',
							'type'    => 'price',
							'description' => 'Digite um valor, por exemplo: 2.50, ou uma porcentagem, por exemplo, 5%. Deixe em branco para desabilitar',
							'desc_tip'    => true,
							'label'   => 'Porcentagem de lucro',
							'default' => (get_option('woocommerce_pac_pl_custom_shipping')) ? get_option('woocommerce_pac_pl_custom_shipping') : 0
						),
						// 'shipping_class' => array(
						// 	'title'       => 'Classe de entrega',
						// 	'type'        => 'select',
						// 	// 'description' => ''
						// 	'desc_tip'    => true,
						// 	'default'     => get_class_option_pac(),
						// 	'class'       => 'wc-enhanced-select',
						// 	'options'     => get_shipping_classes_options(),
						// ),
					);
				}   
			}
		}
	}
	
	function add_pac_shipping_method( $methods ) {
		$methods['pac'] = 'WC_Pac_Shipping_Method';
		return $methods;
	}
	add_filter( 'woocommerce_shipping_methods', 'add_pac_shipping_method' );

}
