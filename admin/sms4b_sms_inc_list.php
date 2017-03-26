<?
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use \Bitrix\Main\Localization\Loc;

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

$sTableID = 'tbl_sms_list_inc';
$oSort = new CAdminSorting($sTableID, 'MOMENT', 'desc');
$lAdmin = new CAdminList($sTableID, $oSort);

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
    'find_GUID',
    'find_Moment_from',
    'find_Moment_to',
    'find_TimeOff_from',
    'find_TimeOff_to',
    'find_Source',
    'find_Destination',
    'find_Body',
    'find_Total'
);

$lAdmin->InitFilter($FilterArr);

if (CheckFilter()) {
    $arFilter = Array(
        'GUID' => $find_GUID,
        '>=MOMENT' => $find_Moment_from,
        '<=MOMENT' => $find_Moment_to,
        '>=TIMEOFF' => $find_LastModified_from,
        '<=TIMEOFF' => $find_LastModified_to,
        'SOURCE' => $find_Source,
        'DESTINATION' => $find_Destination,
        'BODY' => $find_Body,
        'TOTAL' => $find_Total
    );

    foreach ($arFilter as $key => $val) {
        if ($val === null) {
            unset($arFilter[$key]);
        }
    }
}

$lAdmin->AddHeaders(array(
    array('id' => 'GUID', 'content' => Loc::getMessage('SMS4B_MAIN_SMS_GUID'), 'sort' => 'GUID', 'default' => false),
    array(
        'id' => 'MOMENT',
        'content' => Loc::getMessage('SMS4B_MAIN_SMS_MOMENT'),
        'sort' => 'MOMENT',
        'default' => true
    ),
    array(
        'id' => 'TIMEOFF',
        'content' => Loc::getMessage('SMS4B_MAIN_SMS_TIMEOFF'),
        'sort' => 'TIMEOFF',
        'align' => 'right',
        'default' => false
    ),
    array(
        'id' => 'SOURCE',
        'content' => Loc::getMessage('SMS4B_MAIN_SMS_SOURCE'),
        'sort' => 'SOURCE',
        'default' => true
    ),
    array(
        'id' => 'DESTINATION',
        'content' => Loc::getMessage('SMS4B_MAIN_SMS_DESTINATION'),
        'sort' => 'DESTINATION',
        'default' => true
    ),
    array('id' => 'BODY', 'content' => Loc::getMessage('SMS4B_MAIN_SMS_BODY'), 'sort' => 'BODY', 'default' => true),
    array('id' => 'TOTAL', 'content' => Loc::getMessage('SMS4B_MAIN_SMS_TOTAL'), 'sort' => 'TOTAL', 'default' => true)
));

//@TODO - переписать на агенты
//$SMS4B->LoadIncoming();

$rsData = $SMS4B->GetListInc(array($by => $order), $arFilter);
$rsData = new CAdminResult($rsData, $sTableID);

$rsData->NavStart();
$lAdmin->NavText($rsData->GetNavPrint(Loc::getMessage('SMS4B_MAIN_SMS_NAV')));


while ($arRes = $rsData->NavNext(true, 'f_')) {
    $row =& $lAdmin->AddRow($arRes['GUID'], $arRes);
    $row->AddViewField('GUID', $arRes['GUID']);
    $row->AddViewField('MOMENT', $arRes['MOMENT']);
    $row->AddViewField('TIMEOFF', $arRes['TIMEOFF']);
    $row->AddViewField('SOURCE', $arRes['SOURCE']);
    $row->AddViewField('DESTINATION', $arRes['DESTINATION']);

    if ((int)$arRes['CODING'] === 0) {
        $row->AddViewField('BODY', htmlspecialchars(str_replace('
', '<br>', $SMS4B->decode($arRes['BODY'], $arRes['CODING']))));
    } else {
        $row->AddViewField('BODY', str_replace('
', '<br>', $SMS4B->decode($arRes['BODY'], $arRes['CODING'])));
    }
    $row->AddViewField('TOTAL', $arRes['TOTAL']);
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

$lAdmin->CheckListMode();

require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');

$GLOBALS['APPLICATION']->SetTitle(Loc::getMessage('SMS4B_MAIN_SMS4B_TITLE'));

$oFilter = new CAdminFilter(
    $sTableID . '_filter',
    array(
        Loc::getMessage('SMS4B_MAIN_SMS_F_DELIVERY'),
        Loc::getMessage('SMS4B_MAIN_SMS_F_ACTIVE'),
        Loc::getMessage('SMS4B_MAIN_SMS_F_SOURCE'),
        Loc::getMessage('SMS4B_MAIN_SMS_F_DESTINATION'),
        Loc::getMessage('SMS4B_MAIN_SMS_F_TOTAL')
    )
);
?>

<form name="find_form" method="get" action="<?= $GLOBALS['APPLICATION']->GetCurPage(); ?>">
    <? $oFilter->Begin(); ?>
    <tr>
        <td>GUID</td>
        <td>
            <input type="text" name="find_GUID" size="47" value="<? echo htmlspecialchars($find_GUID) ?>"/>
        </td>
    </tr>
    <tr>
        <td><? echo Loc::getMessage('SMS4B_MAIN_SMS_F_MOMENT') . ' (' . CLang::GetDateFormat('FULL') . '):' ?></td>
        <td><? echo CalendarPeriod('find_Moment_from', htmlspecialcharsEx($find_Moment_from), 'find_Moment_to',
                htmlspecialcharsEx($find_StartSend_to),
                'find_form') ?></td>
    </tr>
    <tr>
        <td><? echo Loc::getMessage('SMS4B_MAIN_SMS_F_TIMEOFF') . ' (' . CLang::GetDateFormat('FULL') . '):' ?></td>
        <td><? echo CalendarPeriod('find_TimeOff_from', htmlspecialcharsEx($find_TimeOff_from), 'find_TimeOff_to',
                htmlspecialcharsEx($find_TimeOff_to),
                'find_form') ?></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('SMS4B_MAIN_SMS_F_SOURCE') . ':' ?></td>
        <td><input type="text" name="find_Source" size="47" value="<? echo htmlspecialchars($find_Source) ?>"></td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('SMS4B_MAIN_SMS_F_DESTINATION') . ':' ?></td>
        <td><input type="text" name="find_Destination" size="47" value="<? echo htmlspecialchars($find_Destination) ?>">
        </td>
    </tr>
    <tr>
        <td><?= Loc::getMessage('SMS4B_MAIN_SMS_F_TOTAL') . ':' ?></td>
        <td><input type="text" name="find_Total" size="47" value="<? echo htmlspecialchars($find_Total) ?>"></td>
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

<? $lAdmin->DisplayList(); ?>


<? require($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php'); ?>
