<?php

namespace Services;

/**
 * Class responsible for the shortcode service
 */
class ShortCodeService
{

    public $product;

    /**
     * Constructor
     *
     * @param int $productId
     */
    public function __construct($product)
    {
        $this->product = $product;
    }

    public function shortcode()
    {
        $this->addCalculoDeFrete();
    }

    /**
     * Adiciona o HTML do cálculo de frete na página do produto
     */
    public function addCalculoDeFrete()
    {
        wp_enqueue_script('produto-shortcode', BASEPLUGIN_ASSETS . '/js/shipping-product-page-shortcode.js', 'jquery');
        wp_enqueue_style('calculator-style', BASEPLUGIN_ASSETS . '/css/calculator.css');
        wp_enqueue_script('calculator-script', BASEPLUGIN_ASSETS . '/js/calculator.js');

        echo sprintf(
            "
            <style>
                #melhor-envio-shortcode .border-none,
                tr,
                td {
                    border: 0px;
                }
            </style>
            <div id='melhor-envio-shortcode' class='containerCalculator'>
                <form>
                    <input type='hidden' id='calculo_frete_produto_id' value='%d' />
                    <input type='hidden' id='calculo_frete_url' value='%s' />
                    <div>
                        <table class='border-none'>
                            <tr>
                                <td>
                                    <p>Simulação de frete</p>
                                    <input type='text' maxlength='9' class='iptCepShortcode' placeholder='Informe seu cep' onkeydown='%s'>
                                </td>
                            </tr>
                        </table>
                    </div>
                </form>
                <div id='calcular-frete-loader' style='display:none;'>
                    <img src='https://s3.amazonaws.com/wordpress-v2-assets/img/loader.gif' />
                </div>
                <div class='resultado-frete' style='display:none;'>
                    <table>
                        <thead>
                            <tr>
                                <td><strong>Formas de envios</strong></td>
                            </tr>
                        </thead>
                        <tbody>
                        </tbody>
                    </table>
                </div>
            </div> ",
            $this->product->get_id(),
            admin_url('admin-ajax.php'),
            'return mascara(this, "#####-###")'
        );
    }
}
