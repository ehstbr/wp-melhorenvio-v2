<?php

namespace Services;

class ShippingMelhorEnvioService
{
    const URL_MELHOR_ENVIO_SHIPMENT_SERVICES = 'https://api.melhorenvio.com/v2/me/shipment/services';

    /**
     * function to search for the shipping services available in the Melhor Envio api
     *
     * @return array
     */
    public function getServicesApiMelhorEnvio()
    {
        $response = wp_remote_request(
            self::URL_MELHOR_ENVIO_SHIPMENT_SERVICES
        );

        if (wp_remote_retrieve_response_code($response) != 200) {
            return [];
        }

        return  json_decode(
            wp_remote_retrieve_body(
                $response
            )
        );
    }

    public function getCodesEnableds()
    {
        return $this->getCodesWcShippingClass();
    }

    public function getStringCodesEnables()
    {
        return implode(",",$this->getCodesEnableds());
    }

    public function getCodesWcShippingClass()
    {
        $shippings = WC()->shipping->get_shipping_methods();

        if (is_null($shippings)) { return []; }

        $codes = [];
        
        foreach ($shippings as $method) {
            if (!isset($method->code) || is_null($method->code)) {
                continue;
            }
            $codes[] = $method->code;
        }

        return $codes;
    }
}