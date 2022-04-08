<?php
use Bitrix\Main\Config\Option;
use Bitrix\Main\EventManager;
use Bitrix\Main\Application;

class cdekff extends CModule {

    public function __construct() {
        require __DIR__.'/version.php';
        $this->MODULE_ID = 'cdekff';
        $this->MODULE_VERSION = $arModuleVersion['VERSION'];
        $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
        $this->MODULE_NAME = 'Интеграция с Фулфилмент СДЭК';
        $this->MODULE_DESCRIPTION = 'Модуль для автоматического переноса заказов в Фулфилмент СДЭК';
    }

    public function DoInstall() {
        global $APPLICATION;
        RegisterModule($this->MODULE_ID);
        $this->InstallEvents();

        $APPLICATION->IncludeAdminFile(
            'Установка модуля «'.$this->MODULE_NAME.'»',
            __DIR__.'/step.php'
        );
    }

    public function InstallEvents() {
        EventManager::getInstance()->registerEventHandler(
            'sale',
            'OnSaleOrderSaved',
            $this->MODULE_ID,
            'OrderAdmin\\Main',
            'orderSaved'
        );
    }

    public function DoUninstall() {
        global $APPLICATION;

        $this->UnInstallFiles();
        $this->UnInstallEvents();

        UnRegisterModule($this->MODULE_ID);

        $APPLICATION->IncludeAdminFile(
            'Удаление модуля «'.$this->MODULE_NAME.'»',
            __DIR__.'/unstep.php'
        );
    }

    public function UnInstallFiles() {
        Option::delete($this->MODULE_ID);
    }

    public function UnInstallEvents() {
        EventManager::getInstance()->unRegisterEventHandler(
            'sale',
            'OnSaleOrderSaved',
            $this->MODULE_ID,
            'OrderAdmin\\Main',
            'orderSaved'
        );
    }
}