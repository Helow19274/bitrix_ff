<?php
if (!check_bitrix_sessid())
    return;

if ($errorException = $APPLICATION->getException()) {
    CAdminMessage::showMessage('Ошибка установки модуля: '.$errorException->GetString());
} else {
    CAdminMessage::showNote('Модуль успешно установлен!');
}
?>

<form action="<?= $APPLICATION->getCurPage(); ?>">
    <input type="submit" value="Вернуться к списку модулей">
</form>