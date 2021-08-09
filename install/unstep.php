<?php
if (!check_bitrix_sessid())
    return;

if ($errorException = $APPLICATION->getException()) {
    CAdminMessage::showMessage('Ошибка удаления модуля: '.$errorException->GetString());
} else {
    CAdminMessage::showNote('Модуль успешно удалён');
}
?>

<form action="<?= $APPLICATION->getCurPage(); ?>">
    <input type="submit" value="Вернуться к списку модулей">
</form>