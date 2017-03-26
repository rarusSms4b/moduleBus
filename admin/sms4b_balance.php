<?
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use \Bitrix\Main\Localization\Loc;

Loc::loadLanguageFile(__FILE__);

if ($GLOBALS['APPLICATION']->GetGroupRight('rarus.sms4b') < 'R') {
    $GLOBALS['APPLICATION']->AuthForm(Loc::getMessage('SMS4B_MAIN_ACCESS_DENIED'));
}

global $SMS4B;

$arResult['RESULT_MESSAGE']['TYPE'] = '';

if (empty($SMS4B->LastError) && $SMS4B->GetSOAP('ParamSMS', array('SessionId' => $SMS4B->GetSID())) === true) {
    $arResult['Rest'] = $SMS4B->arBalance['Rest'];
    $arResult['RESULT_MESSAGE']['TYPE'] = 'OK';

    $arResult['Login'] = $SMS4B->getLogin();
} else {
    $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
    $arResult['RESULT_MESSAGE']['MESSAGE'] = Loc::getMessage('SMS4B_MAIN_ERROR_CONNECTION');
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
?>
<form name="form1" method="POST" action="<?= $GLOBALS['APPLICATION']->GetCurPage() ?>">


    <?= bitrix_sessid_post() ?>
    <?
    $aTabs = array(
        array(
            'DIV' => 'edit1',
            'TAB' => Loc::getMessage('SMS4B_MAIN_SMS_LEFT'),
            'ICON' => 'sms4b_balance',
            'TITLE' => Loc::getMessage('SMS4B_MAIN_SMS_LEFT')
        )

    );
    $tabControl = new CAdminTabControl('tabControl', $aTabs);

    $tabControl->Begin();
    $tabControl->BeginNextTab();

    if ($arResult['RESULT_MESSAGE']['TYPE'] === 'OK') {
        ?>
        <tr>
            <td><?= Loc::getMessage('SMS4B_MAIN_NUMBER_SENDER') ?></td>
            <td>
                <table>
                    <tr>
                        <td align="right"><?= Loc::getMessage('SMS4B_MAIN_LOGIN') ?></td>
                        <td><b><?= $arResult['Login'] ?></b></td>
                    </tr>
                    <tr>
                        <td align="right"><?= Loc::getMessage('SMS4B_MAIN_SMS_CAPT'); ?></td>
                        <td>
                            <b><?= round($arResult['Rest'], 2) ?></b>
                            <?= fmod($SMS4B->arBalance['Rest'],
                                1) !== 0 ? Loc::getMessage('SMS4B_MAIN_RUB_PS') : Loc::getMessage('SMS4B_MAIN_SMS_PS'); ?>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
        <?
    } else {
        echo '<tr><td colspan="2">' . CAdminMessage::ShowMessage($arResult['RESULT_MESSAGE']['MESSAGE']) . '</td></tr>';
    }
    $disable = true;
    if (($isAdmin || $isDemo) && $isEditMode) {
        $disable = false;
    }
    $tabControl->Buttons();
    ?>
    <input type="submit" value="<?= Loc::getMessage('SMS4B_MAIN_REFRESH') ?>" name="apply">
    <?
    $tabControl->End();
    ?>
</form>
<? require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'); ?>
