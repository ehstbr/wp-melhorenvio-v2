<?php

namespace Services;

use Models\Order;
use Models\Option;
use Models\Payload;
use Helpers\SessionHelper;
use Helpers\PostalCodeHelper;
use Helpers\CpfHelper;

class CartService
{
    const PLATAFORM = 'WooCommerce V2';

    const ROUTE_MELHOR_ENVIO_ADD_CART = '/cart';

    /**
     * Function to add item on Cart Melhor Envio
     *
     * @param int $orderId
     * @param array $products
     * @param array $dataBuyer
     * @param int $shippingMethodId
     * @return array
     */
    public function add($orderId, $products, $dataBuyer, $shippingMethodId)
    {
        $body = $this->createPayloadToCart($orderId, $products, $dataBuyer, $shippingMethodId);

        $errors = $this->validatePayloadBeforeAddCart($body, $orderId);

        if (!empty($errors)) {
            return [
                'success' => false,
                'errors' => $errors
            ];
        }

        $result = (new RequestService())->request(
            self::ROUTE_MELHOR_ENVIO_ADD_CART,
            'POST',
            $body,
            true
        );

        if (!empty($result->errors)) {
            return [
                'success' => false,
                'errors' => end($result->errors)
            ];
        }

        if (empty($result->id)) {
            return [
                'success' => false,
                'errors' => 'Não foi possível enviar o pedido para o carrinho de compras'
            ];
        }

        return (new OrderQuotationService())->updateDataQuotation(
            $orderId,
            $result->id,
            $result->protocol,
            Order::STATUS_PENDING,
            $shippingMethodId,
            null,
            $result->self_tracking
        );
    }

    /**
     * Function to create payload to insert item on Cart Melhor Envio
     *
     * @param int $orderId
     * @param array $products
     * @param array $dataBuyer
     * @param int $shippingMethodId
     * @return array
     */
    public function createPayloadToCart($orderId, $products, $dataBuyer, $shippingMethodId)
    {
        $payloadSaved = (new Payload())->get($orderId);

        $products = (!empty($payloadSaved->products))
            ? $payloadSaved->products
            : $products;

        $products = $this->removeVirtualProducts($products);

        $dataBuyer = (!empty($payloadSaved->buyer))
            ? $payloadSaved->buyer
            : $dataBuyer;

        $dataFrom =  (new SellerService())->getData();

        $quotation = (new QuotationService())->calculateQuotationByPostId($orderId);

        $orderInvoiceService = new OrderInvoicesService();

        $methodService = new CalculateShippingMethodService();

        $options = (!empty($payloadSaved->options))
            ? $payloadSaved->options
            : (new Option())->getOptions();

        $insuranceRequired = ($methodService->isCorreios($shippingMethodId))
            ? $methodService->insuranceValueIsRequired($options->insurance_value, $shippingMethodId)
            : true;

        $insuranceValue = (!empty($insuranceRequired))
            ? (new ProductsService())->getInsuranceValue($products)
            : 0;

        $payload = array(
            'from' => $dataFrom,
            'to' => $dataBuyer,
            'agency' => $this->getAgencyToInsertCart($shippingMethodId),
            'service' => $shippingMethodId,
            'products' => $products,
            'volumes' => $this->getVolumes($quotation, $shippingMethodId),
            'options' => array(
                "insurance_value" => $insuranceValue,
                "receipt" => $options->receipt,
                "own_hand" => $options->own_hand,
                "collect" => false,
                "reverse" => false,
                "non_commercial" => $orderInvoiceService->isNonCommercial($orderId),
                "invoice" => $orderInvoiceService->getInvoiceOrder($orderId),
                'platform' => self::PLATAFORM,
                'reminder' => null
            )
        );

        return $payload;
    }

    /**
     * 
     * @param array $products
     * @return array
     */
    private function removeVirtualProducts(&$products)
    {
        foreach ($products as $key => $product) {
            if (!empty($product['is_virtual'])) {
                unset($products[$key]);
            }
        }

        return $products;
    }

    /**
     * function to get agency selected by service_Id
     *
     * @param string $shippingMethodId
     * @return int|null
     */
    private function getAgencyToInsertCart($shippingMethodId)
    {
        $shippingMethodService = new CalculateShippingMethodService();

        if ($shippingMethodService->isJadlog($shippingMethodId)) {
            return (new AgenciesJadlogService())->getSelectedAgencyOrAnyByCityUser();
        }

        if ($shippingMethodService->isAzulCargo($shippingMethodId)) {
            return (new AgenciesAzulService())->getSelectedAgencyOrAnyByCityUser();
        }

        if ($shippingMethodService->isLatamCargo($shippingMethodId)) {
            return (new AgenciesLatamService())->getSelectedAgencyOrAnyByCityUser();
        }

        return null;
    }

    /**
     * Function to remove order in cart by Melhor Envio.
     *
     * @param int $postId
     * @param string $orderId
     * @return bool
     */
    public function remove($postId, $orderId)
    {
        (new OrderQuotationService())->removeDataQuotation($postId);

        (new RequestService())->request(
            self::ROUTE_MELHOR_ENVIO_ADD_CART . '/' . $orderId,
            'DELETE',
            []
        );

        $orderInCart = (new OrderService())->info($orderId);

        if (!$orderInCart['success']) {
            return true;
        }

        return false;
    }

    /**
     * Mount array with volumes by products.
     *
     * @param array $quotation
     * @param int $methodid
     * @return array $volumes
     */
    private function getVolumes($quotation, $methodId)
    {
        $volumes = [];

        foreach ($quotation as $item) {
            if (!isset($item->id)) {
                continue;
            }
            if ($item->id == $methodId) {
                foreach ($item->packages as $package) {
                    $volumes[] = [
                        'height' => $package->dimensions->height,
                        'width'  => $package->dimensions->width,
                        'length' => $package->dimensions->length,
                        'weight' => $package->weight,
                    ];
                }
            }
        }

        if ((new CalculateShippingMethodService())->isCorreios($methodId)) {
            return $volumes[0];
        }

        return $volumes;
    }

    /**
     * Function to validate params before send to request
     *
     * @param array $body
     * @return array
     */
    private function validatePayloadBeforeAddCart($body, $orderId)
    {
        $errors = [];

        if (empty($body['service'])) {
            $errors[] = 'Informar o serviço de envio.';
        }

        $isCorreios = (new CalculateShippingMethodService())->isCorreios($body['service']);

        $errors = array_merge($errors, $this->validateAddress('from', 'remetente', $body, $isCorreios));
        $errors = array_merge($errors, $this->validateAddress('to', 'destinatario', $body, $isCorreios));

        if (!$isCorreios && empty($body['agency'])) {
            $errors[] = 'É necessário informar a agência de postagem para esse serviço de envio';
        }

        if (empty($body['products'])) {
            $errors[] = 'É necessário informar os produtos do envio.';
        }

        if (!empty($body['products'])) {
            foreach ($body['products'] as $key => $product) {

                $index = $key++;

                if (empty($product['name'])) {
                    $errors[] = sprintf("Infomar o nome do produto %d", $index);
                }

                if (empty($product['quantity'])) {
                    $errors[] = sprintf("Infomar a quantidade do produto %d", $index);
                }

                if (empty($product['unitary_value'])) {
                    $errors[] = sprintf("Infomar o valor unitário do produto %d", $index);
                }

                if (empty($product['weight'])) {
                    $errors[] = sprintf("Infomar o peso do produto %d", $index);
                }

                if (empty($product['width'])) {
                    $errors[] = sprintf("Infomar a largura do produto %d", $index);
                }

                if (empty($product['height'])) {
                    $errors[] = sprintf("Infomar a altura do produto %d", $index);
                }

                if (empty($product['length'])) {
                    $errors[] = sprintf("Infomar o comprimento do produto %d", $index);
                }
            }
        }

        if (empty($body['options'])) {
            $errors[] = 'Informar os opcionais do envio.';
        }

        if (empty($body['options'])) {
            $errors[] = 'Informar os opcionais do envio.';
        }

        if (empty($body['volumes'])) {
            $errors[] = 'Informar o(s) volume(s) do envio.';
            return $errors;
        }

        if (empty($body['volumes'][0])) {
            $errors = $this->validateVolume($body['volumes'], $errors);
        }

        if (!empty($body['volumes'][0])) {
            foreach ($body['volumes'] as $volume) {
                $errors = $this->validateVolume($volume, $errors);
            }
        }

        return $errors;
    }

    /**
     * Function to validate volume
     * 
     * @param array $volume
     * @param array $errors
     * @return array
     */
    private function validateVolume($volume, $errors)
    {
        if (!empty($volume)) {
            if (empty($volume['height'])) {
                $errors[] ="Informar a altura do volume.";
            }

            if (empty($volume['width'])) {
                $errors[] = "Informar a largura do volume.";
            }

            if (empty($volume['length'])) {
                $errors[] = "Informar o comprimento do volume.";
            }

            if (empty($volume['weight'])) {
                $errors[] = "Informar o peso do volume.";
            }
        }

        return $errors;
    }

    /**
     * Function to validate date address createPayloadToCart
     * 
     * @param string $key
     * @param string $user
     * @param array $body
     * @param bool $isCorreios
     * @return array
     */
    private function validateAddress($key, $user, $body, $isCorreios)
    {
        $errors = [];
    
        if (empty($body[$key])) {
            $errors[] = "Informar o {$user} o pedido.";            
        }

        if (!empty($body[$key]) && empty($body[$key]->name)) {
            $errors[] = "Informar o nome do {$user} do pedido.";
        }

        if (!empty($body[$key]) && empty($body[$key]->phone) && !$isCorreios) {
            $errors[] = "Informar o nome do {$user} do pedido.";
        }

        if (!empty($body[$key]) && empty($body[$key]->email) && !$isCorreios) {
            $errors[] = "Informar o e-mail do {$user} do pedido.";
        }

        if (!empty($body[$key]) && (empty($body[$key]->document) && empty($body[$key]->company_document)) && !$isCorreios) {
            $errors[] = "Informar o documento do {$user} do pedido.";
        }

        if (!empty($body[$key]) && empty($body[$key]->address)) {
            $errors[] = "Informar o endereço do {$user} do pedido.";
        }

        if (!empty($body[$key]) && empty($body[$key]->number)) {
            $errors[] = "Informar o número do endereço do {$user} do pedido.";
        }

        if (!empty($body[$key]) && empty($body[$key]->city)) {
            $errors[] = "Informar a cidade do {$user} do pedido.";
        }

        if (!empty($body[$key]) && empty($body[$key]->state_abbr)) {
            $errors[] = "Informar o estado do {$user} do pedido.";
        }

        if (empty($body[$key]->postal_code)) {
            $errors[] = "Informar o CEP do {$user} do pedido.";
        }

        if (!empty($body[$key]->postal_code)) {
            $body[$key]->postal_code = PostalCodeHelper::postalcode($body[$key]->postal_code);
            if (strlen($body[$key]->postal_code) != PostalCodeHelper::SIZE_POSTAL_CODE) {
                $errors[] = "CEP do {$user} incorreto.";
            }
        }

        return $errors;
      }

    /** 
     * Function to get data and information about items in the shopping cart
     * 
     * @return array
     */
    public function getInfoCart()
    {
        SessionHelper::initIfNotExists();

        global $woocommerce;

        $data = [];

        foreach($woocommerce->cart->get_cart() as $cart) {
            foreach($cart as $item) {
                if (gettype($item) == 'object')  {
                    $productId = $item->get_id();
                    if (!empty($productId)) {
                        $data['products'][$productId] = [
                            'name' => $item->get_name(),
                            'price' => $item->get_price()
                        ];

                        if (!empty($_SESSION['melhorenvio_additional'])) {
                            foreach($_SESSION['melhorenvio_additional'] as $dataSession) {
                                foreach($dataSession as $keyProduct =>$product) {
                                    $data['products'][$productId]['taxas_extras'] = $product;
                                }
                            }
                        }
                    }
                }
            }
        }

        if (!empty($_SESSION['melhorenvio_additional'])) {
            $data['adicionais_extras'] = $_SESSION['melhorenvio_additional'];
        }

        return $data;
    }
}
