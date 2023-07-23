<?php
namespace OrderAdmin;

use Bitrix\Main\Event;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Diag\Debug;

class Main {
    static $MODULE_NAME = "cdekff";

    public static function orderSaved(Event $event) {
        $prefix = null;
        $rsSites = \CSite::GetList($by = "sort", $order = "desc", Array("ACTIVE" => "Y"));
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

        if (Option::get(self::$MODULE_NAME, trim(join('_', [$prefix, 'ORDERADMIN_ENABLED']), ' _')) === 'N')
            return;
        $order = $event->getParameter("ENTITY");
        $oldValues = $event->getParameter("VALUES");
        $allowedStatuses = unserialize(Option::get(self::$MODULE_NAME, trim(join('_', [$prefix, 'ORDERADMIN_ORDER_STATUSES']), ' _')));
        $allowedDeliveries = unserialize(Option::get(self::$MODULE_NAME, trim(join('_', [$prefix, 'ORDERADMIN_ORDER_DELIVERIES']), ' _')));

        if ($order->isCanceled() && $oldValues['CANCELED'] == 'N') {
            $api = new Api(
                Option::get(self::$MODULE_NAME, trim(join('_', [$prefix, 'ORDERADMIN_PUBLIC_KEY']), ' _')),
                Option::get(self::$MODULE_NAME, trim(join('_', [$prefix, 'ORDERADMIN_SECRET']), ' _'))
            );
            $api->cancelOrder($order);
        } else if (!array_key_exists('CANCELED', $oldValues)
            && !$order->isCanceled()
            && in_array($order->getField('STATUS_ID'), $allowedStatuses ?: [])
            && in_array($order->getDeliverySystemId()[0], $allowedDeliveries ?: [])
        ) {
            $api = new Api(
                Option::get(self::$MODULE_NAME, trim(join('_', [$prefix, 'ORDERADMIN_PUBLIC_KEY']), ' _')),
                Option::get(self::$MODULE_NAME, trim(join('_', [$prefix, 'ORDERADMIN_SECRET']), ' _'))
            );
            $api->createOrder($order);
        }
    }
}