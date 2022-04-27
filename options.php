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
        'DIV' => 'edit3',
        'TAB' => 'Способы оплаты',
        'TITLE' => 'Способы оплаты'
    ),
);

$arGroups = array(
    'MAIN' => array('TITLE' => 'Доступ к ФФ', 'TAB' => 0),
    'WAREHOUSE' => array('TITLE' => 'Настройки склада', 'TAB' => 0),
    'ORDERS' => array('TITLE' => 'Заказы', 'TAB' => 0),
    'PAYMENT_SERVICES' => array('TITLE' => 'Способы оплаты', 'TAB' => 3),
);

$countriesSel = $arDSSenders = $arProductsShops = $arOrderPropsSel = $arWarehouses = array(
    'REFERENCE_ID' => array(0),
    'REFERENCE' => array('Выбрать...'),
);

$prefix = null;
$rsSites = CSite::GetList($by = "sort", $order = "desc", Array("ACTIVE" => "Y"));
if ($rsSites->SelectedRowsCount() > 1) {
    while ($arSite = $rsSites->Fetch()) {
        if ($_SERVER['HTTP_HOST'] == $arSite['SERVER_NAME']) {
            break;
        }
    }

    if ($arSite['DEF'] != 'Y') {
        $prefix = strtoupper($arSite['LID']);
    }
}

$arOrderStatusSel = array();
$rsOrderStatuses = CSaleStatus::GetList(array(), array(
    'LID' => LANGUAGE_ID
));

while ($arOrderStatus = $rsOrderStatuses->Fetch()) {
    if (!in_array($arOrderStatus['ID'], $arOrderStatusSel['REFERENCE_ID'])) {
        $arOrderStatusSel['REFERENCE_ID'][] = $arOrderStatus['ID'];
        $arOrderStatusSel['REFERENCE'][] = $arOrderStatus['NAME'];
    }
}

$arOrderDeliverySel = array();
$rsOrderDeliveries = CSaleDelivery::GetList(array(), array(
    'LID' => LANGUAGE_ID
));
while ($arOrderDelivery = $rsOrderDeliveries->Fetch()) {
    if (!in_array($arOrderDelivery['ID'], $arOrderDeliverySel['REFERENCE_ID'])) {
        $arOrderDeliverySel['REFERENCE_ID'][] = $arOrderDelivery['ID'];
        $arOrderDeliverySel['REFERENCE'][] = $arOrderDelivery['NAME'];
    }
}

$publicKey = COption::GetOptionString(ADMIN_MODULE_NAME, join('_', [$prefix, 'ORDERADMIN_PUBLIC_KEY']));
$secret = COption::GetOptionString(ADMIN_MODULE_NAME, join('_', [$prefix, 'ORDERADMIN_SECRET']));

$orderadmin = new \Orderadmin\Api($publicKey, $secret);

if (!empty($publicKey) && !empty($secret)) {
    $res = $orderadmin->request('GET', '/api/locations/countries', array('per_page' => 250))->getResult(true);

    if ($res) {
        foreach ($res['_embedded']['countries'] as $country) {
            $countriesSel['REFERENCE_ID'][] = $country['id'];
            $countriesSel['REFERENCE'][] = $country['name'];
        }
    }

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
    'ORDERADMIN_ORDER_DELIVERIES' => array(
        'GROUP' => 'ORDERS',
        'TITLE' => 'Выгружать заказы только со следующими службами доставки',
        'TYPE' => 'MCHECKBOX',
        'VALUES' => $arOrderDeliverySel,
        'SORT' => '110',
        'NOTES' => 'Можно выбирать только службы доставки официального модуля СДЭК'
    ),
    'ORDERADMIN_PREPAYMENT_SERVICES' => array(
        'GROUP' => 'PAYMENT_SERVICES',
        'TITLE' => 'Оплата при получении (наложенный платёж)',
        'TYPE' => 'MCHECKBOX',
        'VALUES' => $arPaymentServicesSel,
        'SORT' => '20'
    ),
);

if ($prefix) {
    foreach ($arOptions as $key => $value) {
        $arOptions[join('_', [$prefix, $key])] = $value;
        unset($arOptions[$key]);
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