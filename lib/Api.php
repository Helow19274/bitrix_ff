<?php
namespace Orderadmin;

use Bitrix\Main\Loader;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Data\Cache;
use Bitrix\Main\Web\HttpClient;
use Bitrix\Sale\BasketItem;
use Bitrix\Main\Diag\Debug;

if (!Loader::includeModule('catalog') || !Loader::includeModule('sale')) {
    return;
}

class Api {
    static $MODULE_ID = "cdekff";
    static $MODULE_VERSION = "1.0.0";
    static $MODULE_CACHE_PERIOD = 86400;

    protected $publicKey;
    protected $secret;
    protected $json;
    protected $result;
    protected $error;
    protected $httpClient;
    protected $prefix;

    public function __construct($publicKey = null, $secret = null) {
        $this->publicKey = $publicKey;
        $this->secret = $secret;
        $this->httpClient = new HttpClient();
        $this->httpClient->setRedirect(false);
        $this->httpClient->setHeader('Content-Type', 'application/json');
        $this->httpClient->setAuthorization($publicKey, $secret);

        $this->prefix = null;
        $rsSites = \CSite::GetList($by = "sort", $order = "desc", Array("ACTIVE" => "Y"));
        if ($rsSites->SelectedRowsCount() > 1) {
            while ($arSite = $rsSites->Fetch()) {
                if ($_SERVER['HTTP_HOST'] == $arSite['SERVER_NAME']) {
                    break;
                }
            }

            if ($arSite['DEF'] != 'Y') {
                $this->prefix = strtoupper($arSite['LID']);
            }
        }
    }

    public function getResult($decode = false) {
        if ($decode) {
            return json_decode($this->result, true);
        }

        return $this->result;
    }

    public function getError($decode = false) {
        if ($decode) {
            return json_decode($this->error, true);
        }

        return $this->error;
    }

    public function setRequest($array) {
        $this->json = json_encode($array);

        return $this;
    }

    public function getRequest() {
        return $this->json;
    }

    public function request($type, $url, $query = [], $debug = false, $cacheResponse = true) {
        if (!$debug && $type == 'GET') {
            $cacheKeys = [
                'MODULE'  => self::$MODULE_ID,
                'VERSION' => self::$MODULE_VERSION,
                'URL'     => $type,
                'NAME'    => $url,
            ];

            if (!empty($query)) {
                $cacheKeys = array_merge($cacheKeys, $query);
            }

            $obCache = Cache::createInstance();
            $cache = $obCache->initCache(
                self::$MODULE_CACHE_PERIOD, md5(json_encode($cacheKeys)), '/'
            );
        }

        if (!$debug && $cache) {
            $vars = $obCache->getVars();

            if (empty($vars['RESULT'])) {
                $cache = false;
            }
        }

        if ($cacheResponse && $cache) {
            $this->result = $vars["RESULT"];

            $result = $this->getResult(true);

            if (isset($result['total_items'])
                && empty($result['total_items'])
            ) {
                self::request($type, $url, $query, $debug, false);
            }
        } else {
            $url = 'https://cdek.orderadmin.ru' . $url . '?' . http_build_query($query);
            Debug::dumpToFile($url, '', '__bx_log.log');

            $post = $this->getRequest();
            if ($type == 'PATCH')
                $this->httpClient->setHeader('Content-Length', strlen($post));
            $this->httpClient->query($type, $url, $post);

            if ($debug) {
                echo "<pre>";
                echo "<b>" . __FILE__ . "</b><br/>";
                var_dump($url);
                var_dump($post);
                var_dump($this->httpClient);
                var_dump($this->httpClient->getError());
                var_dump($this->httpClient->getHeaders()->toString());
                var_dump($this->httpClient->getStatus());
                var_dump($this->httpClient->getResult());
                var_dump(json_decode($this->httpClient->getResult()));
                echo "</pre>";
                die();
            }

            if (in_array($this->httpClient->getStatus(), [200, 201, 302])) {
                $this->result = $this->httpClient->getResult();

                if ($type == HttpClient::HTTP_GET && !empty($this->result) && $obCache->startDataCache()) {
                    $result = $this->getResult(true);

                    if (!isset($result['total_items']) || $result['total_items'] > 0) {
                        $obCache->endDataCache(["RESULT" => $this->result]);
                    }
                }
            } else {
                $this->error = $this->httpClient->getResult();
                $this->result = false;
            }
        }

        return $this;
    }

    public function cancelOrder($order) {
        $data = [
            'filter' => [[
                'type' => 'eq',
                'field' => 'extId',
                'value' => $order->getId()
            ]]
        ];
        $orderApi = $this->request('GET', '/api/products/order', $data)->getResult(true)['_embedded']['order'];
        if (sizeof($orderApi) == 0) {
            return;
        }
        $orderApi = $orderApi[0];

        $deliveryRequestId = $orderApi['_embedded']['deliveryRequest']['id'];

        $this->setRequest(['state' => 'cancelled'])->request('PATCH', '/api/delivery-services/requests/'.$deliveryRequestId);
        $res = $this->setRequest(['state' => 'cancel'])->request('PATCH', '/api/products/order/'.$orderApi['id'])->getResult(true);
        if (!$res) {
            echo "<pre>";
            echo "<b>" . __FILE__ . "</b><br/>";
            var_dump($this->getError(true));
            echo "</pre>";
            die();
        }
    }

    public function createOrder($order) {
        Debug::dumpToFile('creating order', '', '__bx_log.log');
        $shopId = Option::get(self::$MODULE_ID, trim(join('_', [$this->prefix, 'ORDERADMIN_SHOP']), ' _'));
        $countryId = Option::get(self::$MODULE_ID, trim(join('_', [$this->prefix, 'ORDERADMIN_BASE_COUNTRY']), ' _'));
        $notPaidPS = unserialize(Option::get(self::$MODULE_ID, trim(join('_', [$this->prefix, 'ORDERADMIN_PREPAYMENT_SERVICES']), ' _')));

        $basket = $order->getBasket();

        $propertyCollection = $order->getPropertyCollection();

        $properties = [];
        /** @var \Bitrix\Sale\PropertyValue $property */
        foreach ($propertyCollection as $property) {
            if ($property->getType() == 'ENUM') {
                $properties[$property->getFields()['CODE']] = $property->getProperty(
                )['OPTIONS'][$property->getValue()];
            } else {
                $properties[$property->getFields()['CODE']] = $property->getValue();
            }
        }

        $data = [
            'filter' => [[
                'type' => 'eq',
                'field' => 'extId',
                'value' => $propertyCollection->getDeliveryLocationZip()->getValue()
            ]]
        ];
        $postcode = $this->request(HttpClient::HTTP_GET, '/api/delivery-services/postcodes', $data)->getResult(true);
        if (strval($data['filter'][0]['value']) == '101000') {
            $locality = '62216';
        } else {
            $locality = $postcode['_embedded']['postcodes'][0]['_embedded']['locality']['id'];
        }
	    
        $phone = $propertyCollection->getPhone()->getValue();
        if (strpos($phone, '+') === false) {
            $phone = '+' . $phone;
        }

        $payload = [
            'shop' => $shopId,
            'extId' => $order->getId(),
            'date' => $order->getDateInsert()->toString(),
            'paymentState' => in_array($order->getPaymentSystemId()[0], $notPaidPS) ? 'not_paid' : 'paid',
            'profile' => [
                'name' => $propertyCollection->getProfileName()->getValue(),
                'email' => $propertyCollection->getUserEmail()->getValue(),
            ],
            'phone' => $phone,
            'address' => [
                'country' => $countryId,
                'locality' => $locality,
                'postcode' => $propertyCollection->getDeliveryLocationZip()->getValue(),
                'street' => $properties[Option::get('ipol.sdek', 'street')],
                'house' => $properties[Option::get('ipol.sdek', 'house')],
                'apartment' => $properties[Option::get('ipol.sdek', 'flat')],
            ],
            'eav' => [
                'order-reserve-warehouse' => Option::get(self::$MODULE_ID, trim(join('_', [$this->prefix, 'ORDERADMIN_WAREHOUSE']), ' _')),
            ],
            'deliveryRequest' => [
                'sender' => Option::get(self::$MODULE_ID, trim(join('_', [$this->prefix, 'ORDERADMIN_SENDER']), ' _')),
                'retailPrice' => $order->getDeliveryPrice(),
                'payment' => in_array($order->getPaymentSystemId()[0], $notPaidPS) ? $basket->getPrice() : '0',
            ],
            'orderProducts' => []
        ];
        if ($payload['paymentState'] == 'paid') {
            $payload['deliveryRequest']['retailPrice'] = 0;
        }

        $shipment_collection = $order->getShipmentCollection()->getNotSystemItems();
        $shipment_collection->rewind();
        $track = $shipment_collection->current()->getField('TRACKING_NUMBER');
        if ($track) {
            $payload['deliveryRequest']['trackingNumber'] = $track;
        }

        $pvzProperty = Option::get('ipol.sdek', 'pvzPicker');
        if (!$pvzProperty) {
            $pvzProperty = 'ADDRESS';
        }

        if (strpos($properties[$pvzProperty], '#S') === false) {
            Debug::dumpToFile('courier', '', '__bx_log.log');
            if ($properties['STREET']) {
                $payload['address']['notFormal'] = $properties['STREET'].', '.$properties['HOUSE'].', '.$properties['FLAT'];
            } else {
                $payload['address']['notFormal'] = $properties['ADDRESS'];
            }
            $payload['deliveryRequest']['rate'] = 49;
        } else {
            Debug::dumpToFile('pvz', '', '__bx_log.log');
            $payload['address']['notFormal'] = $properties['IPOLSDEK_PVZ'];
            $payload['deliveryRequest']['rate'] = 48;
            $pvzProperty = explode('#S', $properties[Option::get('ipol.sdek', 'pvzPicker')]);

            $data = [
                'filter' => [[
                    'type' => 'eq',
                    'field' => 'extId',
                    'value' => array_pop($pvzProperty)
                ]]
            ];
            $pvz = $this->request(HttpClient::HTTP_GET, '/api/delivery-services/service-points', $data)->getResult(true);
            $payload['deliveryRequest']['servicePoint'] = $pvz['_embedded']['servicePoints'][0]['id'];
        }

        $basketItems = $basket->getBasketItems();

        /** @var BasketItem $basketItem */
        foreach ($basketItems as $basketItem) {
            $orderProduct = [
                'productOffer' => [
                    'extId' => $basketItem->getProductId(),
                ],
                'count' => $basketItem->getQuantity(),
				'price' => $basketItem->getPrice()
            ];
            array_push($payload['orderProducts'], $orderProduct);
        }

        Debug::dumpToFile(json_encode($payload), '', '__bx_log.log');

        $res = $this->setRequest($payload)->request(HttpClient::HTTP_POST, '/api/products/order')->getResult();
        Debug::dumpToFile(json_encode($res), '', '__bx_log.log');
        Debug::dumpToFile(json_encode($this->getError(true)), '', '__bx_log.log');
        if (!$res) {
            echo "<pre>";
            echo "<b>" . __FILE__ . "</b><br/>";
            var_dump($this->getError(true));
            echo "</pre>";
            die();
        }
    }
}
