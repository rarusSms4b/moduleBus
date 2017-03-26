<?
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Application;
use \Bitrix\Main\Localization\Loc;

$request = Application::getInstance()->getContext()->getRequest();
Loc::loadLanguageFile(__FILE__);

if ($GLOBALS['APPLICATION']->GetGroupRight('rarus.sms4b') < 'R') {
    $GLOBALS['APPLICATION']->AuthForm(Loc::getMessage('SMS4B_MAIN_ACCESS_DENIED'));
}

global $SMS4B;

$GLOBALS['APPLICATION']->SetTitle(Loc::getMessage('SMS4B_MAIN_SMS4B_INDEX_TITLE'));

if ($request->getQuery('mode') === 'list') {
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_js.php');
} else {
    require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
}

if (!empty($SMS4B->LastError) && !$SMS4B->GetSOAP('AccountParams', array('SessionID' => $SMS4B->GetSID())) === true) {
    echo '<tr><td colspan="2">' . CAdminMessage::ShowMessage($SMS4B->LastError . Loc::getMessage('SMS4B_MAIN_M_OPTIONS')) . '</td></tr>';
    return;
}
global $adminPage;
$adminPage->ShowSectionIndex('menu_sms4b', 'rarus.sms4b');

if ($request->getQuery('mode') === 'list') {
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin_js.php');
} else {
    require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
}
