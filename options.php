<?php

use Bitrix\Main\Application;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Loader;

if (!Loader::IncludeModule('iblock') || !Loader::IncludeModule('catalog') || !Loader::IncludeModule('sale') || !Loader::IncludeModule('search') || !Loader::IncludeModule('cdekff')) {
    die('Modules not installed');
}

defined('ADMIN_MODULE_NAME') or define('ADMIN_MODULE_NAME', 'cdekff');

if (!$USER->isAdmin()) {
    $APPLICATION->authForm('Nope');
}

require_once __DIR__.'/include.php';

$arTabs = array(
    array(
        'DIV' => 'edit1',
        'TAB' => 'Настройки',
        'TITLE' => 'Настройки'
    ),
    array(
        'DIV' => 'edit2',
        'TAB' => 'Настройки торгового каталога',
        'TITLE' => 'Настройки торгового каталога'
    ),
    array(
        'DIV' => 'edit3',
        'TAB' => 'Способы оплаты',
        'TITLE' => 'Способы оплаты'
    ),
);

$arGroups = array(
    'MAIN' => array('TITLE' => 'Доступ к Orderadmin', 'TAB' => 0),
    'WAREHOUSE' => array('TITLE' => 'Настройки склада', 'TAB' => 0),
    'ORDERS' => array('TITLE' => 'Заказы', 'TAB' => 0),
    'CATALOG' => array('TITLE' => 'Выбор торгового каталога', 'TAB' => 1),
    'OFFERS' => array('TITLE' => 'Торговые предложения', 'TAB' => 1),
    'ORDER_FIELDS' => array('TITLE' => 'Заказы', 'TAB' => 1),
    'PAYMENT_SERVICES' => array('TITLE' => 'Способы оплаты', 'TAB' => 3),
);

$rsIblock = CCatalog::GetList(array(), array(
    'ACTIVE' => 'Y',
));

$arIblockSel = array(
);

while ($arIblock = $rsIblock->Fetch()) {
    $arIblockSel['REFERENCE_ID'][] = $arIblock['ID'];
    $arIblockSel['REFERENCE'][] = $arIblock['NAME'];
}

$countriesSel = $arDSSenders = $arProductsShops = $arOrderPropsSel = $arWarehouses = array(
    'REFERENCE_ID' => array(0),
    'REFERENCE' => array('Выбрать...'),
);
$arOrderStatusSel = array();

$zip = 0;

$rsOrderProps = CSaleOrderProps::GetList(array(), array(), array('ID', 'NAME'));
while ($arOrderProp = $rsOrderProps->Fetch()) {
    $arOrderPropsSel['REFERENCE_ID'][] = $arOrderProp['ID'];
    $arOrderPropsSel['REFERENCE'][] = '[' . $arOrderProp['ID'] . '] ' . $arOrderProp['NAME'];

    if ($arOrderProp['IS_ZIP'] == 'Y') {
        $zip = $arOrderProp['ID'];
    }
}

$rsOrderStatuses = CSaleStatus::GetList(array(), array(
    'LID' => LANGUAGE_ID,
));
while ($arOrderStatus = $rsOrderStatuses->Fetch()) {
    $arOrderStatusSel['REFERENCE_ID'][] = $arOrderStatus['ID'];
    $arOrderStatusSel['REFERENCE'][] = $arOrderStatus['NAME'];
}

$arOrderDeliverySerivesSel = array();

$rsOrderDeliveryServices = CSaleDelivery::GetList();
while ($arOrderDeliveryService = $rsOrderDeliveryServices->Fetch()) {
    $arOrderDeliverySerivesSel['REFERENCE_ID'][] = $arOrderDeliveryService['ID'];
    $arOrderDeliverySerivesSel['REFERENCE'][] = $arOrderDeliveryService['NAME'];
}

$arDeliveryServicesSel = array();

$obCache = new CPHPCache();
$cache_id = "orderadmin|0.0.3|delivery_services|" . date('d-m-Y');

if ($obCache->InitCache(60 * 60 * 24, $cache_id, "/")) {
    $vars = $obCache->GetVars();

    $deliveryServices = $vars['DELIVERY_SERVICES'];
} else {
    $publicKey = COption::GetOptionString(ADMIN_MODULE_NAME, 'ORDERADMIN_PUBLIC_KEY');
    $secret = COption::GetOptionString(ADMIN_MODULE_NAME, 'ORDERADMIN_SECRET');

    $orderadmin = new \Orderadmin\Api($publicKey, $secret);

    $res = $orderadmin->request('GET', '/delivery-services')->getResult();
    $deliveryServices = json_decode($res, true);

    $obCache->StartDataCache();
    $obCache->EndDataCache(array(
        "DELIVERY_SERVICES" => $deliveryServices
    ));
}

$publicKey = COption::GetOptionString(ADMIN_MODULE_NAME, 'ORDERADMIN_PUBLIC_KEY');
$secret = COption::GetOptionString(ADMIN_MODULE_NAME, 'ORDERADMIN_SECRET');

$orderadmin = new \Orderadmin\Api($publicKey, $secret);

//$loc = \Bitrix\Sale\Location\LocationTable::getByCode(
//    '0000073738', array(
//        'filter' => array('=NAME.LANGUAGE_ID' => LANGUAGE_ID),
//        'select' => array('*', 'NAME_RU' => 'NAME.NAME')
//    )
//)->Fetch();
//
//
//$res = $orderadmin->request(
//    'GET', '/api/locations/localities', array(
//        'criteria' => array(
//            'name'    => $loc['NAME_RU'],
//        ),
//    )
//)->getResult(true);

if (!empty($publicKey) && !empty($secret)) {
    $res = $orderadmin->request('GET', '/api/locations/countries', array('per_page' => 250))->getResult(true);

    if ($res) {
        foreach ($res['_embedded']['countries'] as $country) {
            $countriesSel['REFERENCE_ID'][] = $country['id'];
            $countriesSel['REFERENCE'][] = $country['name'];
        }
    }

    $countryId = Option::get(ADMIN_MODULE_NAME, 'ORDERADMIN_BASE_COUNTRY');

    $res = $orderadmin->request('GET', '/api/delivery-services/senders')->getResult(true);

    if ($res) {
        foreach ($res['_embedded']['senders'] as $sender) {
            $arDSSenders['REFERENCE_ID'][] = $sender['id'];
            $arDSSenders['REFERENCE'][] = sprintf('[%s] %s', $sender['id'], $sender['name']);
        }
    }

    $res = $orderadmin->request('GET', '/api/products/shops')->getResult(true);

    if ($res) {
        foreach ($res['_embedded']['shops'] as $shop) {
            $arProductsShops['REFERENCE_ID'][] = $shop['id'];
            $arProductsShops['REFERENCE'][] = sprintf('[%s] %s', $shop['id'], $shop['name']);
        }
    }

    $res = $orderadmin->request('GET', '/api/storage/warehouse')->getResult(true);

    if ($res) {
        foreach ($res['_embedded']['warehouse'] as $warehouse) {
            $arWarehouses['REFERENCE_ID'][] = $warehouse['id'];
            $arWarehouses['REFERENCE'][] = sprintf('[%s] %s', $warehouse['id'], $warehouse['name']);
        }
    }

    $res = $orderadmin->request('GET', '/api/integrations/sources', array(
        'criteria' => array(
            'handler' => 'bitrix',
            'name' => COption::GetOptionString('main', 'server_name'),
        ),
    ))->getResult(true);

    if ($res['total_items'] == 0) {
        $res = $orderadmin->setRequest(array(
            'handler' => 'bitrix',
            'name' => COption::GetOptionString('main', 'server_name'),
            'settings' => array(
                'url' => sprintf('https://%s', COption::GetOptionString('main', 'server_name')),
            ),
        ))->request('POST', '/api/integrations/sources')->getResult(true);
    }
}

$arPaymentServicesSel = array(
);

$arFilter = array(
    "ACTIVE" => "Y",
);

$dbPaySystem = CSalePaySystem::GetList(array("SORT" => "ASC", "PSA_NAME" => "ASC"), $arFilter);
while ($arPaySystem = $dbPaySystem->Fetch()) {
    $arPaymentServicesSel['REFERENCE_ID'][] = $arPaySystem['ID'];
    $arPaymentServicesSel['REFERENCE'][] = $arPaySystem['NAME'];
}

$catalogId = COption::GetOptionString(ADMIN_MODULE_NAME, 'ORDERADMIN_CATALOG');

$arPropertiesSel = array(
    'REFERENCE_ID' => array('PREVIEW_PICTURE', 'DETAIL_PICTURE'),
    'REFERENCE' => array('Картинка для анонса', 'Детальная картинка'),
);

if (!empty($catalogId)) {
    $rsProperties = CIBlockProperty::GetList(Array("sort" => "asc", "name" => "asc"), Array("ACTIVE" => "Y", "IBLOCK_ID" => $catalogId));
    while ($arProperty = $rsProperties->Fetch()) {
        $arPropertiesSel['REFERENCE_ID'][] = $arProperty['ID'];
        $arPropertiesSel['REFERENCE'][] = $arProperty['NAME'];
    }

    $catalogIBlockProps = CCatalogSKU::GetInfoByProductIBlock($catalogId);

    if (is_numeric($catalogIBlockProps['IBLOCK_ID'])) {
        $arOfferPropertiesSel = array(
            'REFERENCE_ID' => array('PREVIEW_PICTURE', 'DETAIL_PICTURE'),
            'REFERENCE' => array('Картинка для анонса', 'Детальная картинка'),
        );

        $rsProperties = CIBlockProperty::GetList(Array("sort" => "asc", "name" => "asc"), Array("ACTIVE" => "Y", "IBLOCK_ID" => $catalogIBlockProps['IBLOCK_ID']));
        while ($arProperty = $rsProperties->Fetch()) {
            $arOfferPropertiesSel['REFERENCE_ID'][] = $arProperty['ID'];
            $arOfferPropertiesSel['REFERENCE'][] = $arProperty['NAME'];
        }
    }
}

$arOptions = array(
    'ORDERADMIN_ENABLED' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Включить модуль',
        'TYPE' => 'CHECKBOX',
        'VALUE' => 'N',
        'SORT' => '15',
        'NOTES' => ''
    ),
    'ORDERADMIN_PUBLIC_KEY' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Публичный ключ',
        'TYPE' => 'STRING',
        'DEFAULT' => '',
        'SORT' => '15',
        'NOTES' => ''
    ),
    'ORDERADMIN_SECRET' => array(
        'GROUP' => 'MAIN',
        'TITLE' => 'Секретный ключ',
        'TYPE' => 'STRING',
        'DEFAULT' => '',
        'SORT' => '20',
        'NOTES' => ''
    ),
    'ORDERADMIN_BASE_COUNTRY' => array(
        'GROUP' => 'WAREHOUSE',
        'TITLE' => 'Базовая страна',
        'TYPE' => 'SELECT',
        'VALUES' => $countriesSel,
        'DEFAULT' => '',
        'SORT' => '40',
        'NOTES' => ''
    ),
    'ORDERADMIN_SHOP' => array(
        'GROUP' => 'WAREHOUSE',
        'TITLE' => 'Магазин',
        'TYPE' => 'SELECT',
        'VALUES' => $arProductsShops,
        'SORT' => '45',
        'NOTES' => ''
    ),
    'ORDERADMIN_SENDER' => array(
        'GROUP' => 'WAREHOUSE',
        'TITLE' => 'Отправитель',
        'TYPE' => 'SELECT',
        'VALUES' => $arDSSenders,
        'DEFAULT' => '',
        'SORT' => '50',
        'NOTES' => ''
    ),
    'ORDERADMIN_WAREHOUSE' => array(
        'GROUP' => 'WAREHOUSE',
        'TITLE' => 'Склад отгрузки заказов',
        'TYPE' => 'SELECT',
        'VALUES' => $arWarehouses,
        'DEFAULT' => '',
        'SORT' => '50',
        'NOTES' => ''
    ),
    'ORDERADMIN_ORDER_STATUSES' => array(
        'GROUP' => 'ORDERS',
        'TITLE' => 'Выгружать заказы только со следующими статусами',
        'TYPE' => 'MCHECKBOX',
        'VALUES' => $arOrderStatusSel,
        'SORT' => '110',
        'NOTES' => 'Рекомендуется выбрать один статус либо из разных веток статусов (чтобы каждый заказ проходил ровно через один выбранный статус)'
    ),
    'ORDERADMIN_CATALOG' => array(
        'GROUP' => 'CATALOG',
        'TITLE' => 'Торговый каталог',
        'TYPE' => 'SELECT',
        'VALUES' => $arIblockSel,
        'SORT' => '60'
    ),
    'ORDERADMIN_CATALOG_CHECK_PRODUCT_AVAILABILITY' => array(
        'GROUP' => 'CATALOG',
        'TITLE' => 'Проверять наличие товара',
        'TYPE' => 'CHECKBOX',
        'DEFAULT' => 'Y',
        'SORT' => '75'
    ),
    'ORDERADMIN_CATALOG_ARTICLE' => array(
        'GROUP' => 'CATALOG',
        'TITLE' => 'Свойство, содержащее артикул',
        'TYPE' => 'SELECT',
        'VALUES' => $arPropertiesSel,
        'ALLOW_EMPTY' => 'Y',
        'DEFAULT' => '',
        'SORT' => '80',
    ),
    'ORDERADMIN_CATALOG_FIELDS_MODEL' => array(
        'GROUP' => 'CATALOG',
        'TITLE' => 'Поле, содержащее SKU',
        'TYPE' => 'SELECT',
        'VALUES' => $arPropertiesSel,
        'ALLOW_EMPTY' => 'Y',
        'DEFAULT' => '',
        'SORT' => '81'
    ),
    'ORDERADMIN_CATALOG_FIELDS_IMAGE' => array(
        'GROUP' => 'CATALOG',
        'TITLE' => 'Поле с картинкой',
        'TYPE' => 'SELECT',
        'VALUES' => $arPropertiesSel,
        'ALLOW_EMPTY' => 'Y',
        'DEFAULT' => 'PREVIEW_PICTURE',
        'SORT' => '85'
    ),
    'ORDERADMIN_PREPAYMENT_SERVICES' => array(
        'GROUP' => 'PAYMENT_SERVICES',
        'TITLE' => 'Оплата при получении (наложенный платёж)',
        'TYPE' => 'MCHECKBOX',
        'VALUES' => $arPaymentServicesSel,
        'SORT' => '20'
    ),
);

if (is_numeric($catalogIBlockProps['IBLOCK_ID'])) {
    $arOptions = array_merge($arOptions, array(
        'ORDERADMIN_OFFER_FIELDS_MODEL' => array(
            'GROUP' => 'OFFERS',
            'TITLE' => 'Поле, содержащее модель',
            'TYPE' => 'SELECT',
            'VALUES' => $arOfferPropertiesSel,
            'ALLOW_EMPTY' => 'Y',
            'DEFAULT' => '',
            'SORT' => '87'
        ),
        'ORDERADMIN_OFFER_FIELDS_ARTICLE' => array(
            'GROUP' => 'OFFERS',
            'TITLE' => 'Поле, содержащее артикул',
            'TYPE' => 'SELECT',
            'VALUES' => $arOfferPropertiesSel,
            'ALLOW_EMPTY' => 'Y',
            'DEFAULT' => '',
            'SORT' => '87'
        ),
        'ORDERADMIN_OFFER_FIELDS_IMAGE' => array(
            'GROUP' => 'OFFERS',
            'TITLE' => 'Поле с картинкой',
            'TYPE' => 'SELECT',
            'VALUES' => $arOfferPropertiesSel,
            'ALLOW_EMPTY' => 'Y',
            'DEFAULT' => 'PREVIEW_PICTURE',
            'SORT' => '85'
        ),
        'ORDERADMIN_OFFER_ADDITIONAL_FIELDS' => array(
            'GROUP' => 'OFFERS',
            'TITLE' => 'Выгружать дополнительные свойства',
            'TYPE' => 'MSELECT',
            'VALUES' => $arOfferPropertiesSel,
            'ALLOW_EMPTY' => 'N',
            'SORT' => '89'
        ),
    ));
}

$rsSites = CSite::GetList($by = "sort", $order = "desc", Array("ACTIVE" => "Y"));
if ($rsSites->SelectedRowsCount() > 1) {
    $isDefault = false;
    $site = false;
    while ($arSite = $rsSites->Fetch()) {
        if ($_SERVER['HTTP_HOST'] == $arSite['SERVER_NAME']) {
            break;
        }
    }

    if ($arSite['DEF'] != 'Y') {
        $prefix = strtoupper($arSite['LID']);

        foreach ($arOptions as $key => $value) {
            $arOptions[join('_', array($prefix, $key))] = $value;

            unset($arOptions[$key]);
        }
    }
}

$opt = new CModuleOptions(ADMIN_MODULE_NAME, $arTabs, $arGroups, $arOptions, false);
$opt->ShowHTML();

if (!empty($_REQUEST['q'])) {
    $loc = \Bitrix\Sale\Location\LocationTable::getByCode(
        '0000103664', array(
            'filter' => array('=NAME.LANGUAGE_ID' => LANGUAGE_ID),
            'select' => array('*', 'NAME_RU' => 'NAME.NAME')
        )
    )->Fetch();

    $res = $orderadmin->request(
        'GET', '/api/locations/localities', array(
        'filter' => array(
            array(
                'type'  => 'eq',
                'field' => 'name',
                'value' => $loc['NAME_RU'],
            ),
            array(
                'type'   => 'in',
                'field'  => 'type',
                'values' => array(
                    'Город',
                ),
            ),
        ),
    ), true
    )->getResult(true);
}