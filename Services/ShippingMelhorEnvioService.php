<?php

namespace Services;

class ShippingMelhorEnvioService
{
    /**
     * function to get service codes enableds on WooCommerce.
     *
     * @return array
     */
    public function getCodesEnableds()
    {
        return $this->getCodesWcShippingClass();
    }

    /**
     * Function to search the codes of services available in woocommerce in string format separated by commas
     *
     * @return string
     */
    public function getStringCodesEnables()
    {
        return implode(",", $this->getCodesEnableds());
    }

    /**
     * Function to search the codes of services available in woocommerce
     *
     * @return array
     */
    public function getCodesWcShippingClass()
    {
        $shippings = WC()->shipping->get_shipping_methods();

        if (is_null($shippings)) {
            return [];
        }

        $codes = [];

        foreach ($shippings as $method) {
            if (!isset($method->code) || is_null($method->code)) {
                continue;
            }
            $codes[] = $method->code;
        }

        return $codes;
    }

    /**
     * Function to return the "Melhor Envio" shipping methods used 
     * in the delivery zones
     *
     * @return array
     */
    public static function getMethodsActivedsMelhorEnvio() {
      	$methods   = array();
	$shipping_methods = WC()->shipping()->get_shipping_methods();
	foreach ( $shipping_methods as $method ) {
      		if ( isset( $method->enabled ) && 'yes' === $method->enabled ) {
        	$methods[] = $method;
	      }
	}

	return $methods;
  }

    /**
     * Function to check if the method is Melhor Envio.
     *
     * @param object $method
     * @return boolean
     */
    public function isMelhorEnvioMethod($method)
    {
        return (is_numeric(strpos($method->id, 'melhorenvio_')));
    }
}
