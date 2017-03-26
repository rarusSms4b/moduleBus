<?
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use \Bitrix\Main\Localization\Loc;
use \Rarus\Sms4b\Sms4bClient;
use Bitrix\Main\Application;

Loc::loadLanguageFile(__FILE__);

if (!CModule::IncludeModule('rarus.sms4b')) {
    $error = Loc::getMessage('SMS4B_MAIN_CHECK_MODULE_OPT');
} else {
    if ($GLOBALS['APPLICATION']->GetGroupRight('rarus.sms4b') < 'R') {
        $error = Loc::getMessage('SMS4B_MAIN_CHECK_MODULE_OPT');
    }
}

if (!empty($error)) {
    require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');

    $GLOBALS['APPLICATION']->SetTitle(Loc::getMessage('SMS4B_MAIN_SMS4B_TITLE'));
    echo '<tr><td colspan="2">' . CAdminMessage::ShowMessage($error) . '</td></tr>';
    return;
}

$sTableID = 'tbl_sms_list_outgoing';
$oSort = new CAdminSorting($sTableID, 'STARTSEND', 'desc');
$lAdmin = new CAdminList($sTableID, $oSort);
$requestData = Application::getInstance()->getContext()->getRequest();
/**
 * Возвращает результат фильтрации (успешно\нет)
 *
 * @return bool - результат фильтрации (успешно\нет)
 */
function CheckFilter()
{
    global $FilterArr, $lAdmin;
    foreach ($FilterArr as $f) {
        global $f;
    }

    return count($lAdmin->arFilterErrors) === 0;
}

$FilterArr = Array(
    'find_id',
    'find_GUID',
    'find_SenderName',
    'find_Destination',
    'find_StartSend_from',
    'find_StartSend_to',
    'find_LastModified_from',
    'find_LastModified_to',
    'find_CountPart',
    'find_SendPart',
    'find_CodeType',
    'find_TextMessage',
    'find_Sale_Order',
    'find_Posting',
    'find_Events'
);

$lAdmin->InitFilter($FilterArr);

$arFilter = array('*');

if (CheckFilter() && $requestData->getQuery('del_filter') !== 'Y') {
    $arFilter = Array(
        'ID' => $find_id,
        'GUID' => $find_GUID,
        'SENDERNAME' => $find_SenderName,
        'DESTINATION' => $find_Destination,
        '>=STARTSEND' => $find_StartSend_from,
        '<=STARTSEND' => $find_StartSend_to,
        '>=LASTMODIFIED' => $find_LastModified_from,
        '<=LASTMODIFIED' => $find_LastModified_to,
        'COUNTPART' => $find_CountPart,
        'SENDPART' => $find_SendPart,
        'CODETYPE' => $find_CodeType,
        'TEXT' => $find_TextMessage,
        'ORDER_ID' => $find_Sale_Order,
        'POSTING' => $find_Posting,
        'EVENT_NAME' => $find_Events
    );

    //хак, orm не умеет искать string в int поле => выдает все результаты сразу
    foreach ($arFilter as $key => $val) {
        if (($key === 'ORDER_ID'
                || $key === 'POSTING'
                || $key === 'ID'
                || $key === 'STATUS'
                || $key === 'COUNTPART'
                || $key === 'SENDPART'
                || $key === 'CODETYPE')
            && !is_numeric($val)
            && $val !== null
        ) {
            $arFilter[$key] = array();
        } else {
            if ($val === null) {
                unset($arFilter[$key]);
            }
        }
    }
}


$lAdmin->AddHeaders(array(
        array('id' => 'ID', 'content' => 'ID', 'sort' => 'ID', 'default' => true),
        array(
            'id' => 'GUID',
            'content' => Loc::getMessage('SMS4B_MAIN_SMS_GUID'),
            'sort' => 'GUID',
            'default' => false
        ),
        array(
            'id' => 'SENDERNAME',
            'content' => Loc::getMessage('SMS4B_MAIN_SMS_SENDERNAME'),
            'sort' => 'SENDERNAME',
            'default' => true
        ),
        array(
            'id' => 'DESTINATION',
            'content' => Loc::getMessage('SMS4B_MAIN_SMS_DESTINATION'),
            'sort' => 'DESTINATION',
            'align' => 'right',
            'default' => true
        ),
        array(
            'id' => 'STARTSEND',
            'content' => Loc::getMessage('SMS4B_MAIN_SMS_STARTSEND'),
            'sort' => 'STARTSEND',
            'default' => true
        ),
        array(
            'id' => 'LASTMODIFIED',
            'content' => Loc::getMessage('SMS4B_MAIN_SMS_LASTMODIFIED'),
            'sort' => 'LASTMODIFIED',
            'default' => true
        ),
        array(
            'id' => 'CODETYPE',
            'content' => Loc::getMessage('SMS4B_MAIN_SMS_CODETYPE'),
            'sort' => 'CODETYPE',
            'default' => false
        ),
        array(
            'id' => 'TEXTMESSAGE',
            'content' => Loc::getMessage('SMS4B_MAIN_SMS_TEXTMESSAGE'),
            'sort' => 'TEXT',
            'default' => true
        ),
        array(
            'id' => 'SALE_ORDER',
            'content' => Loc::getMessage('SMS4B_MAIN_SMS_SALE_ORDER'),
            'sort' => 'ORDER_ID',
            'default' => true
        ),
        array(
            'id' => 'POSTING',
            'content' => Loc::getMessage('SMS4B_MAIN_SMS_POSTING'),
            'sort' => 'POSTING',
            'default' => true
        ),
        array(
            'id' => 'EVENTS',
            'content' => Loc::getMessage('SMS4B_MAIN_SMS_EVENTS'),
            'sort' => 'EVENT_NAME',
            'default' => true
        ),
        array(
            'id' => 'RESULT',
            'content' => Loc::getMessage('SMS4B_MAIN_SMS_RESULT'),
            'sort' => 'RESULT',
            'default' => true
        )
    )
);

$rsData = $SMS4B->GetList(array(strtoupper($by) => strtoupper($order)), $arFilter);

$rsData = new CAdminResult($rsData, $sTableID);
$rsData->NavStart();
$lAdmin->NavText($rsData->GetNavPrint(Loc::getMessage('SMS4B_MAIN_SMS_NAV')));

while ($arRes = $rsData->NavNext(true, 'f_')) {
    $row =& $lAdmin->AddRow($arRes['ID'], $arRes);
    $row->AddViewField('ID', $arRes['ID']);
    $row->AddViewField('GUID', $arRes['GUID']);
    $row->AddViewField('SENDERNAME', $arRes['SENDERNAME']);
    $row->AddViewField('DESTINATION', $arRes['DESTINATION']);
    $row->AddViewField('STARTSEND', $arRes['STARTSEND']);
    $row->AddViewField('LASTMODIFIED', $arRes['LASTMODIFIED']);
    $row->AddViewField('COUNTPART', $arRes['COUNTPART']);
    $row->AddViewField('SENDPART', $arRes['SENDPART']);
    $row->AddViewField('CODETYPE', empty($arRes['CODETYPE']) ? Loc::getMessage('SMS4B_MAIN_GSM_CODED')
        : Loc::getMessage('SMS4B_MAIN_UNICODE_CODED'));
    $row->AddViewField('TEXTMESSAGE', str_replace('
	', '<br>', $arRes['TEXT']));
    $row->AddViewField('SALE_ORDER', $arRes['ORDER_ID']);
    $row->AddViewField('POSTING', $arRes['POSTING']);
    $row->AddViewField('EVENTS', $arRes['EVENT_NAME']);
    $row->AddViewField('RESULT', \Rarus\Sms4b\Sms4bClient::GetCodeDescription($arRes['RESULT']));
}

$lAdmin->AddFooter(
    array(
        array(
            'title' => Loc::getMessage('SMS4B_MAIN_MAIN_ADMIN_LIST_SELECTED'),
            'value' => $rsData->SelectedRowsCount()
        ),
        array('counter' => true, 'title' => Loc::getMessage('SMS4B_MAIN_MAIN_ADMIN_LIST_CHECKED'), 'value' => '0')
    )
);

$lAdmin->AddAdminContextMenu();

$lAdmin->CheckListMode();

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');

$GLOBALS['APPLICATION']->SetTitle(Loc::getMessage('SMS4B_MAIN_SMS4B_TITLE'));

$oFilter = new CAdminFilter(
    $sTableID . '_filter',
    array(
        Loc::getMessage('SMS4B_MAIN_SMS_F_GUID'),
        Loc::getMessage('SMS4B_MAIN_SMS_F_SENDERNAME'),
        Loc::getMessage('SMS4B_MAIN_SMS_F_DESTINATION'),
        Loc::getMessage('SMS4B_MAIN_SMS_F_STARTSEND_FROM'),
        Loc::getMessage('SMS4B_MAIN_SMS_F_LASTMODIFIED_FROM'),
        Loc::getMessage('SMS4B_MAIN_SMS_F_COUNTPART'),
        Loc::getMessage('SMS4B_MAIN_SMS_F_SENDPART'),
        Loc::getMessage('SMS4B_MAIN_SMS_F_CODETYPE'),
        Loc::getMessage('SMS4B_MAIN_SMS_F_SALE_ORDER'),
        Loc::getMessage('SMS4B_MAIN_SMS_F_POSTING'),
        Loc::getMessage('SMS4B_MAIN_SMS_F_EVENTS')
    )
);
?>

<form name="find_form" method="get" action="<? echo $GLOBALS['APPLICATION']->GetCurPage(); ?>">
    <? $oFilter->Begin(); ?>
    <tr>
        <td>ID</td>
        <td>
            <input type="text" name="find_id" size="47" value="<? echo htmlspecialchars($find_id) ?>">
        </td>
    </tr>
    <tr>
        <td>GUID</td>
        <td>
            <input type="text" name="find_GUID" size="47" value="<? echo htmlspecialchars($find_GUID) ?>">
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('SMS4B_MAIN_SMS_F_SENDERNAME') . ':' ?></td>
        <td><input type="text" name="find_SenderName" size="47" value="<? echo htmlspecialchars($find_SenderName) ?>">
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('SMS4B_MAIN_SMS_F_DESTINATION') . ':' ?></td>
        <td><input type="text" name="find_Destination" size="47" value="<? echo htmlspecialchars($find_Destination) ?>">
        </td>
    </tr>
    <tr>
        <td><? echo Loc::getMessage('SMS4B_MAIN_SMS_F_STARTSEND') . ' (' . CLang::GetDateFormat('FULL') . '):' ?></td>
        <td><? echo CalendarPeriod('find_StartSend_from', htmlspecialcharsEx($find_StartSend_from), 'find_StartSend_to',
                htmlspecialcharsEx($find_StartSend_to),
                'find_form') ?></td>
    </tr>
    <tr>
        <td><? echo Loc::getMessage('SMS4B_MAIN_SMS_F_LASTMODIFIED') . ' (' . CLang::GetDateFormat('FULL') . '):' ?></td>
        <td><? echo CalendarPeriod('find_LastModified_from', htmlspecialcharsEx($find_LastModified_from),
                'find_LastModified_to', htmlspecialcharsEx($find_LastModified_to),
                'find_form') ?></td>
    </tr>

    <tr>
        <td><?= Loc::getMessage('SMS4B_MAIN_SMS_F_COUNTPART') . ':' ?></td>
        <td><input type="text" name="find_CountPart" size="47" value="<? echo htmlspecialchars($find_CountPart) ?>">
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('SMS4B_MAIN_SMS_F_SENDPART') . ':' ?></td>
        <td><input type="text" name="find_SendPart" size="47" value="<? echo htmlspecialchars($find_SendPart) ?>"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('SMS4B_MAIN_SMS_F_CODETYPE') . ':' ?></td>
        <td><input type="text" name="find_CodeType" size="47" value="<? echo htmlspecialchars($find_CodeType) ?>"></td>
    </tr>

    <tr>
        <td><?= Loc::getMessage('SMS4B_MAIN_SMS_F_SALE_ORDER') . ':' ?></td>
        <td><input type="text" name="find_Sale_Order" size="47" value="<? echo htmlspecialchars($find_Sale_Order) ?>">
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('SMS4B_MAIN_SMS_F_POSTING') . ':' ?></td>
        <td><input type="text" name="find_Posting" size="47" value="<? echo htmlspecialchars($find_Posting) ?>"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('SMS4B_MAIN_SMS_F_EVENTS') . ':' ?></td>
        <td><input type="text" name="find_Events" size="47" value="<? echo htmlspecialchars($find_Events) ?>"></td>
    </tr>
    <?
    $oFilter->Buttons(array(
        'table_id' => $sTableID,
        'url' => $GLOBALS['APPLICATION']->GetCurPage(),
        'form' => 'find_form'
    ));
    $oFilter->End();
    ?>
</form>
<?
$lAdmin->DisplayList();
?>

<? require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'); ?>
