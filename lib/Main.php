<?php
namespace OrderAdmin;

use Bitrix\Main\Event;
use Bitrix\Main\Config\Option;
use Bitrix\Main\Diag\Debug;

class Main {
    static $MODULE_NAME = "cdekff";

    public function orderSaved(Event $event) {
        if (Option::get(self::$MODULE_NAME, 'ORDERADMIN_ENABLED') === 'N')
            return;
        $order = $event->getParameter("ENTITY");
        $oldValues = $event->getParameter("VALUES");
        $allowedStatuses = unserialize(Option::get(self::$MODULE_NAME, 'ORDERADMIN_ORDER_STATUSES'));
        $allowedDeliveries = unserialize(Option::get(self::$MODULE_NAME, 'ORDERADMIN_ORDER_DELIVERIES'));

        if ($order->isCanceled() && $oldValues['CANCELED'] == 'N') {
            $api = new Api(
                Option::get(self::$MODULE_NAME, 'ORDERADMIN_PUBLIC_KEY'),
                Option::get(self::$MODULE_NAME, 'ORDERADMIN_SECRET')
            );
            $api->cancelOrder($order);
        } else if (!array_key_exists('CANCELED', $oldValues)
            && !$order->isCanceled()
            && in_array($order->getField('STATUS_ID'), $allowedStatuses)
            && in_array($order->getDeliverySystemId()[0], $allowedDeliveries)
        ) {
            $api = new Api(
                Option::get(self::$MODULE_NAME, 'ORDERADMIN_PUBLIC_KEY'),
                Option::get(self::$MODULE_NAME, 'ORDERADMIN_SECRET')
            );
            $api->createOrder($order);
        }
    }
}