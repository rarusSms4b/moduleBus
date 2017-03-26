<?
use \Bitrix\Main\Localization\Loc;
IncludeModuleLangFile(__FILE__);

if (!function_exists('curl_exec')) {
    echo '<a href=\'http://php.net/manual/ru/book.curl.php\'> ' . Loc::getMessage('SMS4B_MAIN_CURL_LIB_EN') . '</a> '
        . Loc::getMessage('SMS4B_MAIN_CURL_LIB_NOT_INSTALL_EN') . '<br />';
    echo '<a href=\'http://php.net/manual/ru/book.curl.php\'> ' . Loc::getMessage('SMS4B_MAIN_CURL_LIB') . '</a> '
        . Loc::getMessage('SMS4B_MAIN_CURL_LIB_NOT_INSTALL');
    die();
}

$moduleId = 'rarus.sms4b';
Bitrix\Main\Loader::includeModule($moduleId);

$APPLICATION->AddHeadScript('/bitrix/js/' . $moduleId . '/jquery.js');
$APPLICATION->AddHeadScript('/bitrix/js/' . $moduleId . '/jquery.dataTables.js');
$APPLICATION->SetAdditionalCSS('/bitrix/js/' . $moduleId . '/css/styles.css');
CUtil::InitJSCore(array('ajax', 'popup'));


$siteList = array();
$rsSites = CSite::GetList($by = 'sort', $order = 'asc', Array());
while ($arRes = $rsSites->GetNext()) {
    $siteList[] = Array('ID' => $arRes['ID'], 'NAME' => $arRes['NAME']);
}
$siteCount = count($siteList);
$groupRight = $APPLICATION->GetGroupRight($moduleId);

if ($groupRight >= 'R'):

    global $SMS4B;
    $gmt = $SMS4B::getTimeZone();
    $arDefaultSender = array_unique((array)$SMS4B->GetSender());
    $arSonetGroups = $SMS4B->GetSonetGroups();
    $person = $SMS4B->GetPersonTypes();
    $orderProps = $SMS4B->GetSaleOrderProps();
    $customUserTemplates = $SMS4B->GetAllSmsTemplates('SMS4B_USER_LIST_CUSTOM_EVENT');

    $arAllOptions = array(
        array('module_enabled', Loc::getMessage('SMS4B_MAIN_OPT_MODULE_ENABLED'), '', array('checkbox', 'Y')),
        array('proxy_use', Loc::getMessage('SMS4B_MAIN_OPT_PROXY_USE'), 'n', array('checkbox', 'y')),
        array('proxy_host', Loc::getMessage('SMS4B_MAIN_OPT_PROXY_HOST'), '', array('text', 35)),
        array('proxy_port', Loc::getMessage('SMS4B_MAIN_OPT_PROXY_PORT'), '', array('text', 35)),
        array(
            'login',
            Loc::getMessage('SMS4B_MAIN_OPT_LOGIN') . (empty($arDefaultSender[0]) ? Loc::getMessage('SMS4B_MAIN_REGISTER') : ''),
            '',
            array('text', 35)
        ),
        array('password', Loc::getMessage('SMS4B_MAIN_OPT_PASSWORD'), '', array('text', 35)),
        array('gmt', Loc::getMessage('SMS4B_MAIN_OPT_GMT'), 3, array('selectbox', $gmt)),
        array('send_email', Loc::getMessage('SMS4B_MAIN_SEND_EMAIL'), 3, array('checkbox', 'y')),

    );

    $aTabs = array();
    $aTabs[] = array(
        'DIV' => 'edit0',
        'TAB' => Loc::getMessage('SMS4B_MAIN_TAB_PARAM'),
        'ICON' => 'sms4b_settings',
        'TITLE' => Loc::getMessage('SMS4B_MAIN_TAB_TITLE_PARAM')
    );
    $aTabs[] = array(
        'DIV' => 'edit1',
        'TAB' => Loc::getMessage('SMS4B_MAIN_TAB_SITE'),
        'ICON' => 'sms4b_settings',
        'TITLE' => Loc::getMessage('SMS4B_MAIN_TAB_TITLE_SITE')
    );
    $aTabs[] = array(
        'DIV' => 'edit2',
        'TAB' => Loc::getMessage('SMS4B_MAIN_TAB_TEMPLATES'),
        'ICON' => 'sms4b_settings',
        'TITLE' => Loc::getMessage('SMS4B_MAIN_TAB_TITLE_TEMPLATES')
    );
    $aTabs[] = array(
        'DIV' => 'edit3',
        'TAB' => Loc::getMessage('SMS4B_MAIN_TAB_HELP'),
        'ICON' => 'sms4b_settings',
        'TITLE' => Loc::getMessage('SMS4B_MAIN_TAB_TITLE_HELP')
    );
    $aTabs[] = array(
        'DIV' => 'edit4',
        'TAB' => Loc::getMessage('SMS4B_MAIN_TAB_SUPPORT'),
        'ICON' => 'sms4b_settings',
        'TITLE' => Loc::getMessage('SMS4B_MAIN_TAB_TITLE_SUPPORT')
    );
    $aTabs[] = array(
        'DIV' => 'edit5',
        'TAB' => Loc::getMessage('SMS4B_MAIN_TAB_RIGHTS'),
        'ICON' => 'sms4b_settings',
        'TITLE' => Loc::getMessage('SMS4B_MAIN_TAB_TITLE_RIGHTS')
    );
    $aTabs[] = array(
        'DIV' => 'edit6',
        'TAB' => Loc::getMessage('SMS4B_MAIN_TAB_LOG'),
        'ICON' => 'sms4b_settings',
        'TITLE' => Loc::getMessage('SMS4B_MAIN_TAB_TITLE_LOG')
    );

    $tabControl = new CAdminTabControl('tabControl', $aTabs);
    if ($groupRight >= 'W' && $REQUEST_METHOD === 'POST' && strlen($Update . $RestoreDefaults) > 0 && check_bitrix_sessid()) {

        if (strlen($RestoreDefaults) > 0) {
            //хак, при очистке данных модуля нельзя удалять ID агента (ссылка на него не сможет формироваться),
            //а отдельных методов для исключения нет
            $agentID = $SMS4B->GetCurrentOption('deadline_agent_id', SITE_ID);

            COption::RemoveOption($moduleId);
            $APPLICATION->DelGroupRight($moduleId);

            COption::SetOptionString($moduleId, 'deadline_agent_id', $agentID);
        } else {
            foreach ($arAllOptions as $arOption) {
                $name = $arOption[0];
                $val = $_REQUEST[$name];
                if ($val !== 'Y' && $arOption[2][0] === 'checkbox') {
                    $val = 'N';
                }
                COption::SetOptionString($moduleId, $name, $val, $arOption[1]);
            }

            $SMS4B->CloseSID();
            $SMS4B = new Csms4b();
            $arDefaultSender = $SMS4B->GetSender();

            if (Bitrix\Main\Loader::includeModule('sale')) {
                $arStatus = $SMS4B->GetSaleStatus();
                $arSaleStatus = '';
                $arAdminStatus = '';
                foreach ($arStatus as $status) {
                    $arSaleStatus[] = 'event_sale_status_' . $status['ID'];
                    $arAdminStatus[] = 'admin_event_sale_status_' . $status['ID'];
                }
            }

            for ($i = 0; $i < $siteCount; $i++) {
                if (empty($_REQUEST['defsender'][$siteList[$i]['ID']])) {
                    $_REQUEST['defsender'][$siteList[$i]['ID']] = $arDefaultSender[0];
                }
                COption::SetOptionString($moduleId, 'log_enable', trim($_REQUEST['log_enable'][$siteList[$i]['ID']]),
                    Loc::getMessage('SMS4B_MAIN_LOG_ENABLE'), $siteList[$i]['ID']);
                COption::SetOptionString($moduleId, 'use_translit',
                    trim($_REQUEST['use_translit'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_USE_TRANSLIT'),
                    $siteList[$i]['ID']);
                if (in_array(trim($_REQUEST['defsender'][$siteList[$i]['ID']]), $arDefaultSender)) {
                    COption::SetOptionString($moduleId, 'defsender', trim($_REQUEST['defsender'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_OPT_DEFSENDER'), $siteList[$i]['ID']);
                } else {
                    COption::SetOptionString($moduleId, 'defsender', $arDefaultSender[0], Loc::getMessage('SMS4B_MAIN_OPT_DEFSENDER'),
                        $siteList[$i]['ID']);
                }

                COption::SetOptionString($moduleId, 'phone_number_code',
                    trim($_REQUEST['phone_number_code'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_PHONE_NUMBER_CODE'),
                    $siteList[$i]['ID']);
                COption::SetOptionString($moduleId, 'user_property_phone',
                    trim($_REQUEST['user_property_phone'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_USER_PHONE'),
                    $siteList[$i]['ID']);

                COption::SetOptionString($moduleId, 'restricted_time',
                    trim($_REQUEST['DATE_TO_NS_' . $siteList[$i]['ID']] . $_REQUEST['DATE_FROM_NS_' . $siteList[$i]['ID']]),
                    Loc::getMessage('SMS4B_MAIN_RESTRICTED_TIME'), $siteList[$i]['ID']);

                if (IsModuleInstalled('subscribe')) {
                    COption::SetOptionString($moduleId, 'event_subscribe_confirm',
                        trim($_REQUEST['event_subscribe_confirm'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_OPT_SUBSCRIBE_CONFIRM'), $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_event_subscribe_confirm',
                        trim($_REQUEST['admin_event_subscribe_confirm'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_OPT_SUBSCRIBE_CONFIRM'), $siteList[$i]['ID']);
                }

                if (IsModuleInstalled('im')) {
                    COption::SetOptionString($moduleId, 'event_autoanswer',
                        trim($_REQUEST['event_autoanswer'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_OPT_AUTOANSWER'), $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'event_missed_call',
                        trim($_REQUEST['event_missed_call'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_OPT_MISSING_CALL'), $siteList[$i]['ID']);
                }

                COption::SetOptionString($moduleId, 'admin_phone', trim($_REQUEST['admin_phone'][$siteList[$i]['ID']]),
                    Loc::getMessage('SMS4B_MAIN_ADMIN_PHONE'), $siteList[$i]['ID']);


                if (IsModuleInstalled('tasks')) {
                    if (empty($_REQUEST['workGroups_' . $siteList[$i]['ID']])) {
                        foreach ($SMS4B->GetSonetGroups() as $val) {
                            $_REQUEST['workGroups_' . $siteList[$i]['ID']][] = $val['ID'];
                        }
                    }
                    COption::SetOptionString($moduleId, 'serialize_work_groups', serialize($_REQUEST['workGroups_' . $siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_WG_ID'), $siteList[$i]['ID']);
                }

                if (IsModuleInstalled('sale')) {
                    COption::SetOptionString($moduleId, 'event_sale_new_order',
                        trim($_REQUEST['event_sale_new_order'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_OPT_NEW_ORDER'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'event_sale_order_paid',
                        trim($_REQUEST['event_sale_order_paid'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_OPT_ORDER_PAID'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'event_sale_order_cancel',
                        trim($_REQUEST['event_sale_order_cancel'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_OPT_ORDER_CANCEL'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'event_sale_order_delivery',
                        trim($_REQUEST['event_sale_order_delivery'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_OPT_ORDER_DELIVERY'), $siteList[$i]['ID']);

                    COption::SetOptionString($moduleId, 'admin_event_sale_new_order',
                        trim($_REQUEST['admin_event_sale_new_order'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_OPT_NEW_ORDER'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_event_sale_order_paid',
                        trim($_REQUEST['admin_event_sale_order_paid'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_OPT_ORDER_PAID'), $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_event_sale_order_cancel',
                        trim($_REQUEST['admin_event_sale_order_cancel'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_OPT_ORDER_CANCEL'), $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_event_sale_order_delivery',
                        trim($_REQUEST['admin_event_sale_order_delivery'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_OPT_ORDER_DELIVERY'), $siteList[$i]['ID']);
                }

                if (IsModuleInstalled('support')) {
                    COption::SetOptionString($moduleId, 'admin_event_ticket_new_for_techsupport',
                        trim($_REQUEST['admin_event_ticket_new_for_techsupport'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_OPT_TICKET_NEW_FOR_TECHSUPPORT'), $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'event_ticket_new_for_techsupport',
                        trim($_REQUEST['event_ticket_new_for_techsupport'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_OPT_TICKET_NEW_FOR_TECHSUPPORT'), $siteList[$i]['ID']);
                }
                if (IsModuleInstalled('tasks')) {
                    COption::SetOptionString($moduleId, 'add_low_task',
                        trim($_REQUEST['add_low_task'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_ADD_LOW_TASK'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'add_middle_task',
                        trim($_REQUEST['add_middle_task'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_ADD_MIDDLE_TASK'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'add_hight_task',
                        trim($_REQUEST['add_hight_task'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_ADD_HIGHT_TASK'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'update_low_task',
                        trim($_REQUEST['update_low_task'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_UPDATE_LOW_TASK'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'update_middle_task',
                        trim($_REQUEST['update_middle_task'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_UPDATE_MIDDLE_TASK'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'update_hight_task',
                        trim($_REQUEST['update_hight_task'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_UPDATE_HIGHT_TASK'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'delete_low_task',
                        trim($_REQUEST['delete_low_task'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_DELETE_LOW_TASK'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'delete_middle_task',
                        trim($_REQUEST['delete_middle_task'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_DELETE_MIDDLE_TASK'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'delete_hight_task',
                        trim($_REQUEST['delete_hight_task'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_DELETE_HIGHT_TASK'),
                        $siteList[$i]['ID']);

                    COption::SetOptionString($moduleId, 'intercept_deadline',
                        trim($_REQUEST['intercept_deadline'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_INTERCEPT_DEADLINE'), $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'new_comment_task',
                        trim($_REQUEST['new_comment_task'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_NEW_COMM_FROM_TASK'), $siteList[$i]['ID']);

                    if (empty($_REQUEST['intercept_deadline'][$siteList[$i]['ID']])) {
                        COption::SetOptionString($moduleId, 'deadline_date', '',
                            Loc::getMessage('SMS4B_MAIN_INTERCEPT_DEADLINE'));
                    }

                    COption::SetOptionString($moduleId, 'admin_add_low_task',
                        trim($_REQUEST['admin_add_low_task'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_ADD_LOW_TASK'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_add_middle_task',
                        trim($_REQUEST['admin_add_middle_task'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_ADD_MIDDLE_TASK'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_add_hight_task',
                        trim($_REQUEST['admin_add_hight_task'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_ADD_HIGHT_TASK'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_update_low_task',
                        trim($_REQUEST['admin_update_low_task'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_UPDATE_LOW_TASK'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_update_middle_task',
                        trim($_REQUEST['admin_update_middle_task'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_UPDATE_MIDDLE_TASK'), $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_update_hight_task',
                        trim($_REQUEST['admin_update_hight_task'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_UPDATE_HIGHT_TASK'), $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_delete_low_task',
                        trim($_REQUEST['admin_delete_low_task'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_DELETE_LOW_TASK'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_delete_middle_task',
                        trim($_REQUEST['admin_delete_middle_task'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_DELETE_MIDDLE_TASK'), $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_delete_hight_task',
                        trim($_REQUEST['admin_delete_hight_task'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_DELETE_HIGHT_TASK'), $siteList[$i]['ID']);
                }

                if (Bitrix\Main\Loader::includeModule('crm')) {
                    /* Отправка пользователю */

                    //Контакты
                    COption::SetOptionString($moduleId, 'add_contact_crm',
                        trim($_REQUEST['add_contact_crm'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_ADD_CONTACT_CRM'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'update_contact_crm',
                        trim($_REQUEST['update_contact_crm'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_CHANGE_CONTACT_CRM'), $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'remove_contact_crm',
                        trim($_REQUEST['remove_contact_crm'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_DEL_CONTACT_CRM'),
                        $siteList[$i]['ID']);
                    //Дела
                    COption::SetOptionString($moduleId, 'remind_event_crm',
                        trim($_REQUEST['remind_event_crm'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_REMIND_EVENT_CRM'),
                        $siteList[$i]['ID']);
                    //Лид
                    COption::SetOptionString($moduleId, 'add_lead_crm',
                        trim($_REQUEST['add_lead_crm'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_ADD_LEAD_CRM'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'update_lead_crm',
                        trim($_REQUEST['update_lead_crm'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_CHANGE_LEAD_CRM'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'delete_lead_crm',
                        trim($_REQUEST['delete_lead_crm'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_DEL_LEAD_CRM'),
                        $siteList[$i]['ID']);

                    foreach (CCrmStatus::GetStatusListEx('STATUS') as $id => $title) {
                        COption::SetOptionString($moduleId, 'change_stat_lead_crm_' . $id,
                            trim($_REQUEST['change_stat_lead_crm_' . $id][$siteList[$i]['ID']]),
                            Loc::getMessage('SMS4B_MAIN_CH_STATUS_LEAD_CRM'), $siteList[$i]['ID']);
                    }

                    //Сделка
                    COption::SetOptionString($moduleId, 'add_deal_crm',
                        trim($_REQUEST['add_deal_crm'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_ADD_DEAL_CRM'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'update_deal_crm',
                        trim($_REQUEST['update_deal_crm'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_CHANGE_DEAL_CRM'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'delete_deal_crm',
                        trim($_REQUEST['delete_deal_crm'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_DEL_DEAL_CRM'),
                        $siteList[$i]['ID']);

                    foreach (CCrmStatus::GetStatusListEx('DEAL_STAGE') as $id => $title) {
                        COption::SetOptionString($moduleId, 'change_stat_deal_crm_' . $id,
                            trim($_REQUEST['change_stat_deal_crm_' . $id][$siteList[$i]['ID']]),
                            Loc::getMessage('SMS4B_MAIN_CH_STATUS_DEAL_CRM'), $siteList[$i]['ID']);
                    }

                    /* Отправка администратору */

                    //Контакты
                    COption::SetOptionString($moduleId, 'admin_add_contact_crm',
                        trim($_REQUEST['admin_add_contact_crm'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_ADD_CONTACT_CRM'), $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_update_contact_crm',
                        trim($_REQUEST['admin_update_contact_crm'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_CHANGE_CONTACT_CRM'), $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_remove_contact_crm',
                        trim($_REQUEST['admin_remove_contact_crm'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_DEL_CONTACT_CRM'), $siteList[$i]['ID']);
                    //Дела
                    COption::SetOptionString($moduleId, 'admin_remind_event_crm',
                        trim($_REQUEST['admin_remind_event_crm'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_REMIND_EVENT_CRM'), $siteList[$i]['ID']);
                    //Лид
                    COption::SetOptionString($moduleId, 'admin_add_lead_crm',
                        trim($_REQUEST['admin_add_lead_crm'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_ADD_LEAD_CRM'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_update_lead_crm',
                        trim($_REQUEST['admin_update_lead_crm'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_CHANGE_LEAD_CRM'), $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_delete_lead_crm',
                        trim($_REQUEST['admin_delete_lead_crm'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_DEL_LEAD_CRM'),
                        $siteList[$i]['ID']);

                    foreach (CCrmStatus::GetStatusListEx('STATUS') as $id => $title) {
                        COption::SetOptionString($moduleId, 'admin_change_stat_lead_crm_' . $id,
                            trim($_REQUEST['admin_change_stat_lead_crm_' . $id][$siteList[$i]['ID']]),
                            Loc::getMessage('SMS4B_MAIN_CH_STATUS_LEAD_CRM'), $siteList[$i]['ID']);
                    }

                    //Сделка
                    COption::SetOptionString($moduleId, 'admin_add_deal_crm',
                        trim($_REQUEST['admin_add_deal_crm'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_ADD_DEAL_CRM'),
                        $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_update_deal_crm',
                        trim($_REQUEST['admin_update_deal_crm'][$siteList[$i]['ID']]),
                        Loc::getMessage('SMS4B_MAIN_CHANGE_DEAL_CRM'), $siteList[$i]['ID']);
                    COption::SetOptionString($moduleId, 'admin_delete_deal_crm',
                        trim($_REQUEST['admin_delete_deal_crm'][$siteList[$i]['ID']]), Loc::getMessage('SMS4B_MAIN_DEL_DEAL_CRM'),
                        $siteList[$i]['ID']);

                    foreach (CCrmStatus::GetStatusListEx('DEAL_STAGE') as $id => $title) {
                        COption::SetOptionString($moduleId, 'admin_change_stat_deal_crm_' . $id,
                            trim($_REQUEST['admin_change_stat_deal_crm_' . $id][$siteList[$i]['ID']]),
                            Loc::getMessage('SMS4B_MAIN_CH_STATUS_DEAL_CRM'), $siteList[$i]['ID']);
                    }
                }

                foreach ($arSaleStatus as $option) {
                    COption::SetOptionString($moduleId, $option, trim($_REQUEST[$option][$siteList[$i]['ID']]), $option,
                        $siteList[$i]['ID']);
                }
                foreach ($arAdminStatus as $option) {
                    COption::SetOptionString($moduleId, $option, trim($_REQUEST[$option][$siteList[$i]['ID']]), $option,
                        $siteList[$i]['ID']);
                }
            }
        }
    }

    $tabControl->Begin(); ?>
    <form method='post'
          action='<?= $APPLICATION->GetCurPage() ?>?mid=<?= urlencode($mid) ?>&amp;lang=<?= LANGUAGE_ID ?>'>
        <?
        $login = $SMS4B->getLogin();
        $pass = $SMS4B->getPassword();

        if (!empty($login) && !empty($pass)) {
            if (!empty($arDefaultSender[0])) {
                $message = new CAdminMessage(array(
                    'MESSAGE' => Loc::getMessage('SMS4B_MAIN_SUCCESS_CONNECT'),
                    'TYPE' => 'OK',
                    'HTML' => true
                ));
            } else {
                $message = new CAdminMessage(array(
                    'MESSAGE' => Loc::getMessage('SMS4B_MAIN_NONE_CONNECT') . '<br>' . Loc::getMessage('SMS4B_MAIN_REGISTRY_INFORMATION'),
                    'TYPE' => 'ERROR',
                    'HTML' => true
                ));
            }
        } else {
            $message = new CAdminMessage(array(
                'MESSAGE' => Loc::getMessage('SMS4B_MAIN_NO_LOG_AND_PASS') . '<br>' . Loc::getMessage('SMS4B_MAIN_REGISTRY_INFORMATION'),
                'TYPE' => 'ERROR',
                'HTML' => true
            ));
        }

        if(!class_exists('SoapClient'))
        {
            $soapNoEnable = new CAdminMessage(array(
                'MESSAGE' => Loc::getMessage('SMS4B_MAIN_SOAP_NOT_EXIST'),
                'TYPE' => 'ERROR',
                'HTML' => true
            ));
        }

        ?>
        <div class='adm-detail-content'>
            <? echo !empty($soapNoEnable) ? $soapNoEnable->Show() : $message->Show()?>
        </div>
        <?

        $tabControl->BeginNextTab();
        foreach ($arAllOptions as $arOption):
            __AdmSettingsDrawRow($moduleId, $arOption);
        endforeach;

        if (empty($login) && empty($pass)) {
            echo "<script>$(\"input[name='login']\").focus();</script>";
        }

        $tabControl->BeginNextTab();
        ?>
        <tr>
            <td colspan='2' valign='top'>
                <?
                $aTabs3 = Array();
                foreach ($siteList as $val) {
                    $aTabs3[] = Array(
                        'DIV' => 'options' . $val['ID'],
                        'TAB' => '[' . $val['ID'] . '] ' . $val['NAME'],
                        'TITLE' => Loc::getMessage('SMS4B_MAIN_SITE_TITLE') . '[' . $val['ID'] . '] ' . $val['NAME']
                    );
                }
                $tabControl3 = new CAdminViewTabControl('tabControl3', $aTabs3);
                $tabControl3->Begin();

                for ($i = 0; $i < $siteCount; $i++):
                    $tabControl3->BeginNextTab();


                    $defsender = COption::GetOptionString($moduleId, 'defsender', '', $siteList[$i]['ID']);
                    $allTemplates = $SMS4B->GetAllSmsTemplates(false, $siteList[$i]['ID']);
                    $customOrderTemplates = $SMS4B->GetAllSmsTemplates('SMS4B_USER_CUSTOM_EVENT', $siteList[$i]['ID']);
                    $useTranslit = COption::GetOptionString($moduleId, 'use_translit', '', $siteList[$i]['ID']);
                    $admin_phone = COption::GetOptionString($moduleId, 'admin_phone', '', $siteList[$i]['ID']);
                    $defUserProperty = COption::GetOptionString($moduleId, 'user_property_phone', false,
                        $siteList[$i]['ID']);
                    $log_enable = COption::GetOptionString($moduleId, 'log_enable', '', $siteList[$i]['ID']);
                    $restrictedTime = COption::GetOptionString($moduleId, 'restricted_time', '', $siteList[$i]['ID']);

                    global $USER;
                    $rsUser = CUser::GetList(($by = 'ID'), ($order = 'desc'), array('ID' => 1),
                        array('SELECT' => array('UF_*')));
                    $arUser = $rsUser->Fetch();
                    $arUserPhone[] = '';
                    foreach ($arUser as $index => $value) {
                        $pattern = '/(PERSONAL|WORK|UF|^LOGIN$)/';

                        if (preg_match($pattern, $index)) {
                            $arUserPhone[] = $index;
                        }
                    }

                    if (Bitrix\Main\Loader::includeModule('subscribe')) {
                        $event_subscribe_confirm = COption::GetOptionString($moduleId, 'event_subscribe_confirm', '',
                            $siteList[$i]['ID']);
                        $admin_event_subscribe_confirm = COption::GetOptionString($moduleId,
                            'admin_event_subscribe_confirm', '', $siteList[$i]['ID']);
                    }

                    if (IsModuleInstalled('im')) {
                        $event_autoanswer = COption::GetOptionString($moduleId, 'event_autoanswer', '', $siteList[$i]['ID']);
                        $event_missed_call = COption::GetOptionString($moduleId, 'event_missed_call', '', $siteList[$i]['ID']);
                    }

                    if (Bitrix\Main\Loader::includeModule('sale')) {
                        $event_sale_new_order = COption::GetOptionString($moduleId, 'event_sale_new_order', '',
                            $siteList[$i]['ID']);
                        $event_sale_order_paid = COption::GetOptionString($moduleId, 'event_sale_order_paid', '',
                            $siteList[$i]['ID']);
                        $event_sale_order_delivery = COption::GetOptionString($moduleId, 'event_sale_order_delivery',
                            '', $siteList[$i]['ID']);
                        $event_sale_order_cancel = COption::GetOptionString($moduleId, 'event_sale_order_cancel', '',
                            $siteList[$i]['ID']);

                        $admin_event_sale_new_order = COption::GetOptionString($moduleId, 'admin_event_sale_new_order',
                            '', $siteList[$i]['ID']);
                        $admin_event_sale_order_paid = COption::GetOptionString($moduleId,
                            'admin_event_sale_order_paid', '', $siteList[$i]['ID']);
                        $admin_event_sale_order_delivery = COption::GetOptionString($moduleId,
                            'admin_event_sale_order_delivery', '', $siteList[$i]['ID']);
                        $admin_event_sale_order_cancel = COption::GetOptionString($moduleId,
                            'admin_event_sale_order_cancel', '', $siteList[$i]['ID']);

                        $orderPhoneCode = COption::GetOptionString($moduleId, 'phone_number_code', false,
                            $siteList[$i]['ID']);

                        $arSaleStatus = $arAdminStatus = array();
                        foreach((array)$SMS4B->GetSaleStatus() as $status){
                            $arSaleStatus[$status['TYPE']][] = array(
                                'event_sale_status_' . $status['ID'] => COption::GetOptionString($moduleId,
                                    'event_sale_status_' . $status['ID'], '', $siteList[$i]['ID']),
                                'NAME' => $status['NAME'],
                                'ID' => $status['ID']
                            );

                            $arAdminStatus[$status['TYPE']][] = array(
                                'admin_event_sale_status_' . $status['ID'] => COption::GetOptionString($moduleId,
                                    'admin_event_sale_status_' . $status['ID'], '', $siteList[$i]['ID']),
                                'NAME' => $status['NAME'],
                                'ID' => $status['ID']
                            );
                        }
                    }
                    if (Bitrix\Main\Loader::includeModule('tasks')) {
                        $add_low_task = COption::GetOptionString($moduleId, 'add_low_task', '', $siteList[$i]['ID']);
                        $add_middle_task = COption::GetOptionString($moduleId, 'add_middle_task', '',
                            $siteList[$i]['ID']);
                        $add_hight_task = COption::GetOptionString($moduleId, 'add_hight_task', '',
                            $siteList[$i]['ID']);
                        $update_low_task = COption::GetOptionString($moduleId, 'update_low_task', '',
                            $siteList[$i]['ID']);
                        $update_middle_task = COption::GetOptionString($moduleId, 'update_middle_task', '',
                            $siteList[$i]['ID']);
                        $update_hight_task = COption::GetOptionString($moduleId, 'update_hight_task', '',
                            $siteList[$i]['ID']);
                        $delete_low_task = COption::GetOptionString($moduleId, 'delete_low_task', '',
                            $siteList[$i]['ID']);
                        $delete_middle_task = COption::GetOptionString($moduleId, 'delete_middle_task', '',
                            $siteList[$i]['ID']);
                        $delete_hight_task = COption::GetOptionString($moduleId, 'delete_hight_task', '',
                            $siteList[$i]['ID']);

                        $intercept_deadline = COption::GetOptionString($moduleId, 'intercept_deadline', '',
                            $siteList[$i]['ID']);
                        $new_comment_task = COption::GetOptionString($moduleId, 'new_comment_task', '',
                            $siteList[$i]['ID']);

                        $admin_add_low_task = COption::GetOptionString($moduleId, 'admin_add_low_task', '',
                            $siteList[$i]['ID']);
                        $admin_add_middle_task = COption::GetOptionString($moduleId, 'admin_add_middle_task', '',
                            $siteList[$i]['ID']);
                        $admin_add_hight_task = COption::GetOptionString($moduleId, 'admin_add_hight_task', '',
                            $siteList[$i]['ID']);
                        $admin_update_low_task = COption::GetOptionString($moduleId, 'admin_update_low_task', '',
                            $siteList[$i]['ID']);
                        $admin_update_middle_task = COption::GetOptionString($moduleId, 'admin_update_middle_task', '',
                            $siteList[$i]['ID']);
                        $admin_update_hight_task = COption::GetOptionString($moduleId, 'admin_update_hight_task', '',
                            $siteList[$i]['ID']);
                        $admin_delete_low_task = COption::GetOptionString($moduleId, 'admin_delete_low_task', '',
                            $siteList[$i]['ID']);
                        $admin_delete_middle_task = COption::GetOptionString($moduleId, 'admin_delete_middle_task', '',
                            $siteList[$i]['ID']);
                        $admin_delete_hight_task = COption::GetOptionString($moduleId, 'admin_delete_hight_task', '',
                            $siteList[$i]['ID']);
                    }

                    if (IsModuleInstalled('tasks')) {

                        $arUnserWG = unserialize(COption::GetOptionString($moduleId, 'serialize_work_groups', '',
                            $siteList[$i]['ID']));
                        foreach ($arSonetGroups as $key => $group) {
                            if (in_array($group['ID'], (array)$arUnserWG)) {
                                $arSonetGroups[$key]['ENABLE'] = true;
                            }
                            else
                            {
                                unset($arSonetGroups[$key]['ENABLE']);
                            }
                        }
                    }

                    if (IsModuleInstalled('crm')) {
                        /* Отправка пользователю */

                        //Контакты
                        $add_contact_crm = COption::GetOptionString($moduleId, 'add_contact_crm', '',
                            $siteList[$i]['ID']);
                        $update_contact_crm = COption::GetOptionString($moduleId, 'update_contact_crm', '',
                            $siteList[$i]['ID']);
                        //Дела
                        $remind_event_crm = COption::GetOptionString($moduleId, 'remind_event_crm', '',
                            $siteList[$i]['ID']);
                        //Лид
                        $add_lead_crm = COption::GetOptionString($moduleId, 'add_lead_crm', '', $siteList[$i]['ID']);
                        $update_lead_crm = COption::GetOptionString($moduleId, 'update_lead_crm', '',
                            $siteList[$i]['ID']);
                        $delete_lead_crm = COption::GetOptionString($moduleId, 'delete_lead_crm', '',
                            $siteList[$i]['ID']);

                        foreach (CCrmStatus::GetStatusListEx('STATUS') as $id => $title) {
                            $change_stat_lead_crm[$id] = COption::GetOptionString($moduleId,
                                'change_stat_lead_crm_' . $id,
                                '', $siteList[$i]['ID']);
                        }


                        //Сделка
                        $add_deal_crm = COption::GetOptionString($moduleId, 'add_deal_crm', '', $siteList[$i]['ID']);
                        $update_deal_crm = COption::GetOptionString($moduleId, 'update_deal_crm', '',
                            $siteList[$i]['ID']);
                        $change_stat_deal_crm = COption::GetOptionString($moduleId, 'change_stat_deal_crm', '',
                            $siteList[$i]['ID']);
                        foreach (CCrmStatus::GetStatusListEx('DEAL_STAGE') as $id => $title) {
                            $change_stat_deal_crm[$id] = COption::GetOptionString($moduleId,
                                'change_stat_deal_crm_' . $id,
                                '', $siteList[$i]['ID']);
                        }

                        /* Отправка администратору */

                        //Контакты
                        $admin_add_contact_crm = COption::GetOptionString($moduleId, 'admin_add_contact_crm', '',
                            $siteList[$i]['ID']);
                        $admin_update_contact_crm = COption::GetOptionString($moduleId, 'admin_update_contact_crm', '',
                            $siteList[$i]['ID']);
                        $admin_remove_contact_crm = COption::GetOptionString($moduleId, 'admin_remove_contact_crm', '',
                            $siteList[$i]['ID']);
                        //Дела
                        $admin_remind_event_crm = COption::GetOptionString($moduleId, 'admin_remind_event_crm', '',
                            $siteList[$i]['ID']);
                        //Лид
                        $admin_add_lead_crm = COption::GetOptionString($moduleId, 'admin_add_lead_crm', '',
                            $siteList[$i]['ID']);
                        $admin_update_lead_crm = COption::GetOptionString($moduleId, 'admin_update_lead_crm', '',
                            $siteList[$i]['ID']);
                        $admin_delete_lead_crm = COption::GetOptionString($moduleId, 'admin_delete_lead_crm', '',
                            $siteList[$i]['ID']);

                        foreach (CCrmStatus::GetStatusListEx('STATUS') as $id => $title) {
                            $admin_change_stat_lead_crm[$id] = COption::GetOptionString($moduleId,
                                'admin_change_stat_lead_crm_' . $id, '', $siteList[$i]['ID']);
                        }
                        //Сделка
                        $admin_add_deal_crm = COption::GetOptionString($moduleId, 'admin_add_deal_crm', '',
                            $siteList[$i]['ID']);
                        $admin_update_deal_crm = COption::GetOptionString($moduleId, 'admin_update_deal_crm', '',
                            $siteList[$i]['ID']);
                        $admin_delete_deal_crm = COption::GetOptionString($moduleId, 'admin_delete_deal_crm', '',
                            $siteList[$i]['ID']);

                        foreach (CCrmStatus::GetStatusListEx('DEAL_STAGE') as $id => $title) {
                            $admin_change_stat_deal_crm[$id] = COption::GetOptionString($moduleId,
                                'admin_change_stat_deal_crm_' . $id, '', $siteList[$i]['ID']);
                        }

                    }

                    if (Bitrix\Main\Loader::includeModule('support')) {
                        $event_ticket_new_for_techsupport = COption::GetOptionString($moduleId,
                            'event_ticket_new_for_techsupport', '', $siteList[$i]['ID']);
                        $admin_event_ticket_new_for_techsupport = COption::GetOptionString($moduleId,
                            'admin_event_ticket_new_for_techsupport', '', $siteList[$i]['ID']);
                    }

                    ?>
                    <table cellpadding='2' cellspacing='2' border='0' width='100%' align='center'>


                        <tr>
                            <td align='center' colspan='2'>

                                <?
                                $arTabsEvents = array(
                                    array(
                                        'DIV' => 'edit_event0_' . $siteList[$i]['ID'],
                                        'TAB' => Loc::getMessage('SMS4B_MAIN_SUB_TAB_TITLE_ALL')
                                    )
                                );
                                if (IsModuleInstalled('sale')) {
                                    $arTabsEvents[] = array(
                                        'DIV' => 'edit_event1_' . $siteList[$i]['ID'],
                                        'TAB' => Loc::getMessage('SMS4B_MAIN_SUB_TAB_TITLE_SALE')
                                    );
                                }
                                if (IsModuleInstalled('tasks')) {
                                    $arTabsEvents[] = array(
                                        'DIV' => 'edit_event2_' . $siteList[$i]['ID'],
                                        'TAB' => Loc::getMessage('SMS4B_MAIN_SUB_TAB_TITLE_TASKS')
                                    );
                                }
                                if (IsModuleInstalled('crm')) {
                                    $arTabsEvents[] = array(
                                        'DIV' => 'edit_event3_' . $siteList[$i]['ID'],
                                        'TAB' => Loc::getMessage('SMS4B_MAIN_SUB_TAB_TITLE_CRM')
                                    );
                                }
                                if (IsModuleInstalled('voximplant')) {
                                    $arTabsEvents[] = array(
                                        'DIV' => 'edit_event4_' . $siteList[$i]['ID'],
                                        'TAB' => Loc::getMessage('SMS4B_MAIN_SUB_TAB_TITLE_IM')
                                    );
                                }
                                $arTabsEvents[] = array(
                                    'DIV' => 'edit_event5_' . $siteList[$i]['ID'],
                                    'TAB' => Loc::getMessage('SMS4B_MAIN_SUB_TAB_TITLE_STANDART')
                                );

                                $tabEventsControl = new CAdminTabControl('tabEventsControl_' . $siteList[$i]['ID'], $arTabsEvents, false);
                                $tabEventsControl->Begin(); ?>
                                <? $tabEventsControl->BeginNextTab(); ?>

                        <tr class='heading'>
                            <td colspan='2'><?= Loc::getMessage('SMS4B_MAIN_TAB_SEND') ?></td>
                        </tr>
                        <tr>
                            <td valign='center' align='right'><? echo Loc::getMessage('SMS4B_MAIN_USE_TRANSLIT'); ?></td>
                            <td valign='top'><input type='checkbox' name='use_translit[<?= $siteList[$i]['ID'] ?>]'
                                                    value='Y'<?= ($useTranslit === 'Y' ? " checked = \"checked\" " : '') ?>/>
                            </td>
                        </tr>

                        <tr>
                            <td valign='center' align='right' width='50%'><? echo Loc::getMessage('SMS4B_MAIN_OPT_DEFSENDER'); ?></td>
                            <td valign='top'>
                                <select name='defsender[<?= $siteList[$i]['ID'] ?>]'>
                                    <? foreach ($arDefaultSender as $sender): ?>
                                        <option
                                            value='<?= $sender ?>'<?= ($sender === $defsender ? " selected=\"selected\"" : '') ?>><?= $sender ?></option>
                                    <? endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <td valign='center' align='right'><? echo Loc::getMessage('SMS4B_MAIN_USER_PHONE'); ?></td>
                            <td valign='top'>
                                <select name='user_property_phone[<?= $siteList[$i]['ID'] ?>]'>

                                    <? foreach ($arUserPhone as $value): ?>
                                        <? if (!empty($value)): ?>
                                            <option
                                                value='<?= $value ?>'<?= ($value === $defUserProperty ? " selected=\"selected\"" : '') ?>><?= $value ?></option>
                                        <? endif; ?>
                                    <? endforeach; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <input type='checkbox' id='ACTIVE_NIGHT_TIME_NS_<?= $siteList[$i]['ID'] ?>' name='ACTIVE_NIGHT_TIME_NS[<?= $siteList[$i]['ID'] ?>]' value='Y'
                                    <? if (!empty($restrictedTime)): ?> checked <? endif; ?> />
                                <label for='ACTIVE_NIGHT_TIME_NS_<?= $siteList[$i]['ID'] ?>'><?= Loc::getMessage('SMS4B_MAIN_NIGHT_TIME_NS') ?></label>
                            </td>
                            <script>
                                $('#ACTIVE_NIGHT_TIME_NS_<?= $siteList[$i]['ID'] ?>').click(function () {
                                    if ($(this).is(':checked') == true) {
                                        $('#DATE_FROM_NS_<?= $siteList[$i]['ID'] ?>').removeAttr('disabled');
                                        $('#DATE_TO_NS_<?= $siteList[$i]['ID'] ?>').removeAttr('disabled');
                                    }
                                    else {
                                        $('#DATE_FROM_NS_<?= $siteList[$i]['ID'] ?>').attr('disabled', true);
                                        $('#DATE_TO_NS_<?= $siteList[$i]['ID'] ?>').attr('disabled', true);
                                    }
                                });
                            </script>
                            <td>
                                <select id='DATE_FROM_NS_<?= $siteList[$i]['ID'] ?>'
                                        name='DATE_FROM_NS_<?= $siteList[$i]['ID'] ?>' <? if (empty($restrictedTime)): ?> disabled <? endif; ?>>
                                    <? for ($s = 0; $s < 24; $s++): ?>
                                        <option
                                            value='<?= chr(65 + $s) ?>' <? if (chr(65 + $s) == $restrictedTime[0]): ?> selected <? endif; ?> >
                                            <?= $s ?>:00
                                        </option>
                                    <? endfor; ?>
                                </select>
                                <?= Loc::getMessage('SMS4B_MAIN_NIGHT_TIME_TO') ?>
                                <select id='DATE_TO_NS_<?= $siteList[$i]['ID'] ?>'
                                        name='DATE_TO_NS_<?= $siteList[$i]['ID'] ?>' <? if (empty($restrictedTime)): ?> disabled <? endif; ?> >
                                    <? for ($s = 0; $s < 24; $s++): ?>
                                        <option
                                            value='<?= chr(65 + $s) ?>' <? if (chr(65 + $s) == $restrictedTime[1]): ?> selected <? endif; ?> >
                                            <?= $s ?>:59
                                        </option>
                                    <? endfor; ?>
                                </select>
                            </td>
                        </tr>

                        <tr>
                            <td valign='top' align='right'><? echo Loc::getMessage('SMS4B_MAIN_ADMIN_PHONE'); ?></td>
                            <td valign='top'><textarea name='admin_phone[<?= $siteList[$i]['ID'] ?>]' cols='20'
                                                       rows='3'><?= $admin_phone ?></textarea></td>
                        </tr>

                        <? if (IsModuleInstalled('sale')): ?>
                            <? $tabEventsControl->BeginNextTab(); ?>
                            <tr>
                                <td valign='center' align='right'><? echo Loc::getMessage('SMS4B_MAIN_PHONE_NUMBER_CODE_SALE'); ?></td>
                                <td valign='top'>
                                    <select name='phone_number_code[<?= $siteList[$i]['ID'] ?>]'>
                                        <? foreach ($orderProps as $code => $arProps): ?>
                                            <option
                                                value='<?= $code ?>'<?= ($code === $orderPhoneCode ? " selected=\"selected\"" : '') ?>>
                                                <?= $arProps[0]['NAME'] . (count($arProps) === 1 ? ' (' . $person[$arProps[0]['PERSON_TYPE_ID']] . ')' : '') ?></option>
                                        <? endforeach; ?>
                                    </select>
                                </td>
                            </tr>
                            <tr class='heading'>
                                <td align='center' colspan='2'><?= Loc::getMessage('SMS4B_MAIN_TAB_EVENTS_USER') ?></td>
                            </tr>
                            <tr>
                                <td align='center' colspan='2'>
                                    <table class='displayEvAdmin'>
                                        <tr>
                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'><?= Loc::getMessage('SMS4B_MAIN_TITLE_SALE') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_OPT_NEW_ORDER'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='event_sale_new_order[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($event_sale_new_order === 'Y' ? " checked = \"checked\" " : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_SALE_NEW_ORDER'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_OPT_ORDER_PAID'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='event_sale_order_paid[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($event_sale_order_paid === 'Y' ? " checked = \"checked\" " : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_SALE_ORDER_PAID'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_OPT_ORDER_DELIVERY'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='event_sale_order_delivery[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($event_sale_order_delivery === 'Y' ? " checked = \"checked\" " : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_SALE_ORDER_DELIVERY'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_OPT_ORDER_CANCEL'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='event_sale_order_cancel[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($event_sale_order_cancel === 'Y' ? " checked = \"checked\" " : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_SALE_ORDER_CANCEL'][0]['ID'] ?>
                                                                target=' _blank'
                                                            title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                </table>
                                            </td>

                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'><?= Loc::getMessage('SMS4B_MAIN_CH_STATUSES_SALE') ?></td>
                                                    </tr>
                                                    <? foreach ($arSaleStatus['O'] as $status): ?>
                                                        <tr>
                                                            <td align='left'><?= $status['NAME'] ?></td>
                                                            <td><input type='checkbox'
                                                                       name='<?= key($status); ?>[<?= $siteList[$i]['ID'] ?>]'
                                                                       value='Y'<?= ($status[key($status)] === 'Y' ? " checked = \"checked\" " : '') ?>/>
                                                            </td>
                                                            <td valign='top'><a
                                                                    href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_SALE_STATUS_CHANGED_' . $status['ID']][0]['ID'] ?>'
                                                                    target='_blank'
                                                                    title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                        src='/bitrix/images/fileman/edit_text.gif'></a>
                                                            </td>
                                                        </tr>
                                                    <? endforeach; ?>
                                                    </td></tr>
                                                </table>
                                            </td>

                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'><?= Loc::getMessage('SMS4B_MAIN_CH_STATUSES_SALE_DELIVERY') ?></td>
                                                    </tr>
                                                    <? foreach ($arSaleStatus['D'] as $status): ?>
                                                        <tr>
                                                            <td align='left'><?= $status['NAME'] ?></td>
                                                            <td><input type='checkbox'
                                                                       name='<?= key($status); ?>[<?= $siteList[$i]['ID'] ?>]'
                                                                       value='Y'<?= ($status[key($status)] === 'Y' ? " checked = \"checked\" " : '') ?>/>
                                                            </td>
                                                            <td valign='top'><a
                                                                    href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_SALE_STATUS_CHANGED_' . $status['ID']][0]['ID'] ?>'
                                                                    target='_blank'
                                                                    title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                        src='/bitrix/images/fileman/edit_text.gif'></a>
                                                            </td>
                                                        </tr>
                                                    <? endforeach; ?>
                                                    </td></tr>
                                                </table>
                                            </td>

                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr class='heading'>
                                <td colspan='2'><?= Loc::getMessage('SMS4B_MAIN_TAB_EVENTS_SHOP') ?></td>
                            </tr>
                            <tr>
                                <td align='center' colspan='2'>
                                    <table class='displayEvAdmin'>
                                        <tr>
                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'><?= Loc::getMessage('SMS4B_MAIN_TITLE_SALE') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_OPT_NEW_ORDER'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_event_sale_new_order[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_event_sale_new_order === 'Y' ? " checked = \"checked\" " : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_SALE_NEW_ORDER'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_OPT_ORDER_PAID'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_event_sale_order_paid[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_event_sale_order_paid === 'Y' ? " checked = \"checked\" " : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_SALE_ORDER_PAID'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_OPT_ORDER_DELIVERY'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_event_sale_order_delivery[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_event_sale_order_delivery === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_SALE_ORDER_DELIVERY'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_OPT_ORDER_CANCEL'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_event_sale_order_cancel[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_event_sale_order_cancel === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_SALE_ORDER_CANCEL'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                </table>
                                            </td>

                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'><?= Loc::getMessage('SMS4B_MAIN_CH_STATUSES_SALE') ?></td>
                                                    </tr>
                                                    <? foreach ($arAdminStatus['O'] as $status): ?>
                                                        <tr>
                                                            <td align='left'><?= $status['NAME'] ?></td>
                                                            <td><input type='checkbox'
                                                                       name='<?= key($status); ?>[<?= $siteList[$i]['ID'] ?>]'
                                                                       value='Y'<?= ($status[key($status)] === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                            </td>
                                                            <td valign='top'><a
                                                                    href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_SALE_STATUS_CHANGED_' . $status['ID']][0]['ID'] ?>'
                                                                    target='_blank'
                                                                    title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                        src='/bitrix/images/fileman/edit_text.gif'></a>
                                                            </td>
                                                        </tr>
                                                    <? endforeach; ?>
                                                    </td></tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr class='heading'>
                                <td colspan='2'><?= Loc::getMessage('SMS4B_MAIN_TAB_CUSTOM_TEMPLATES') ?></td>
                            </tr>
                            <tr>
                                <td align='center' valign='top' colspan='2'>
                                    <table width='240px'>
                                        <tr class='heading'>
                                            <td colspan='3'><?= Loc::getMessage('SMS4B_MAIN_CUSTOM_TEMPLATES') ?></td>
                                        </tr>

                                        <script>
                                            $(function () {
                                                $('#createSms4bUserTemplateButton_<?=$siteList[$i]['ID']?>').click(function () {

                                                    BX.ajax({
                                                        method: 'POST',
                                                        url: '/bitrix/admin/sms4b_addTemplate.php',
                                                        data: {
                                                            text: '<?=Loc::getMessage('SMS4B_MAIN_USER_CUSTOM_TEMPLATE_TEXT')?>',
                                                            subject: '<?=Loc::getMessage('SMS4B_MAIN_USER_CUSTOM_TEMPLATE_SUBJECT')?>',
                                                            emailFrom: '<?=Loc::getMessage('SMS4B_MAIN_USER_CUSTOM_TEMPLATE_EMAIL_FROM')?>',
                                                            emailTo: '<?=Loc::getMessage('SMS4B_MAIN_USER_CUSTOM_TEMPLATE_EMAIL_TO')?>',
                                                            eventType: 'USER_CUSTOM_EVENT',
                                                            site: '<?=$siteList[$i]['ID']?>'
                                                        },
                                                        onsuccess: function (id) {
                                                            if (id > 0) {
                                                                $('#addOrderTemplateButton_<?=$siteList[$i]['ID']?>').before('<tr id="customTemplate' + id + '"><td valign="top" align="left"><?=Loc::getMessage('SMS4B_MAIN_USER_CUSTOM_TEMPLATE_TEXT')?></td>' +
                                                                    '<td valign="top"><a href="/bitrix/admin/message_edit.php?ID=' + id + '" target="_blank" title="<?=Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK')?>"><img src="/bitrix/images/fileman/edit_text.gif"></a></td>' +
                                                                    '<td valign="top"><a href="javascript:void(0);" id=\'' + id + '\' class=\'deleteSms4bTemplateButton\' title="<?=Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_DEL_LINK')?>"><img src="/bitrix/images/main/del.gif"></a></td></tr>'
                                                                );
                                                            }
                                                        }
                                                    })
                                                })

                                                $('body').on('click', '.deleteSms4bTemplateButton', function () {
                                                    var id = $(this).get(0).id;

                                                    BX.ajax({
                                                        method: 'POST',
                                                        url: '/bitrix/admin/sms4b_delTemplate.php',
                                                        data: {templateId: id},
                                                        onsuccess: function () {
                                                            $("#customTemplate" + id).remove();
                                                        }
                                                    })
                                                })
                                            })
                                        </script>
                                        <? foreach ((array)$customOrderTemplates['SMS4B_USER_CUSTOM_EVENT'] as $val): ?>
                                            <tr id='customTemplate<?= $val['ID'] ?>'>
                                                <td valign='top' align='left'><?= $val['NAME']; ?></td>
                                                <td valign='top'><a
                                                        href='/bitrix/admin/message_edit.php?ID=<?= $val['ID'] ?>'
                                                        target='_blank'
                                                        title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                            src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                <td valign='top'><a href='javascript:void(0);' id='<?= $val['ID'] ?>'
                                                                    class='deleteSms4bTemplateButton'
                                                                    title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_DEL_LINK') ?>'><img
                                                            src='/bitrix/images/main/del.gif'></a></td>
                                            </tr>
                                        <? endforeach; ?>

                                        <tr id='addOrderTemplateButton_<?=$siteList[$i]['ID']?>'>
                                            <td align='center' colspan='3'>
                                                <a id='createSms4bUserTemplateButton_<?=$siteList[$i]['ID']?>' hidefocus='true'
                                                   class='adm-btn'><?= Loc::getMessage('SMS4B_MAIN_ADD_CUSTOM_TEMPLATE') ?></a>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>


                        <? endif; ?>

                        <? if (IsModuleInstalled('tasks')): ?>
                            <? $tabEventsControl->BeginNextTab(); ?>
                            <? if (!empty($arSonetGroups)): ?>
                            <tr>
                                <td valign='center' align='right'><? echo Loc::getMessage('SMS4B_MAIN_WORK_GROUP_TASKS'); ?></td>
                                <td valign='top'>
                                    <select id='sms4b_wg_ids_<?= $siteList[$i]['ID'] ?>' size='4' multiple='multiple' name='workGroups_<?= $siteList[$i]['ID'] ?>[]'>
                                        <? foreach ($arSonetGroups as $group): ?>
                                                <option <?= (!empty($group['ENABLE']) ? ' selected=\'selected\'' : '') ?>
                                                    value='<?= $group['ID'] ?>'><?= $group['NAME'] ?></option>
                                        <? endforeach; ?>
                                    </select>
                                    <a id='sms4b_wg_ch_all_<?= $siteList[$i]['ID'] ?>'
                                       style='cursor: default'><?= Loc::getMessage('SMS4B_MAIN_CH_ALL') ?></a>
                                </td>
                            </tr>

                            <script>
                                $(document).ready(function () {
                                    $('#sms4b_wg_ch_all_<?= $siteList[$i]['ID'] ?>').click(function () {
                                        $('#sms4b_wg_ids_<?= $siteList[$i]['ID'] ?> option').each(function () {
                                            this.selected = true;
                                        });
                                    });
                                });
                            </script>
                        <?endif;
                        ?>

                            <tr class='heading'>
                                <td align='center' colspan='2'><?= Loc::getMessage('SMS4B_MAIN_TAB_EVENTS_USER') ?></td>
                            </tr>
                            <tr>
                                <td align='center' colspan='2'>
                                    <table class='displayEvAdmin'>
                                        <tr>
                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_ADD_TASK') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_LOW_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='add_low_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($add_low_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_TASK_ADD'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_MIDDLE_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='add_middle_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($add_middle_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_TASK_ADD'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_HIGHT_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='add_hight_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($add_hight_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_TASK_ADD'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                </table>
                                            </td>

                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'
                                                            align='center'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_UPDATE_TASK') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_LOW_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='update_low_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($update_low_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_TASK_UPDATE'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_MIDDLE_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='update_middle_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($update_middle_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_TASK_UPDATE'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_HIGHT_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='update_hight_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($update_hight_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_TASK_UPDATE'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                </table>
                                            </td>

                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'
                                                            align='center'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_DELETE_TASK') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_LOW_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='delete_low_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($delete_low_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_TASK_DELETE'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_MIDDLE_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='delete_middle_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($delete_middle_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_TASK_DELETE'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_HIGHT_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='delete_hight_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($delete_hight_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_TASK_DELETE'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                </table>
                                            </td>

                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'
                                                            align='center'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_TASK_HANDLER') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_INTERCEPT_DEADLINE'); ?>
                                                            <a href='/bitrix/admin/agent_edit.php?ID=<?= $SMS4B->GetCurrentOption('deadline_agent_id'); ?>'
                                                               target='_blank'>
                                                                <mark title='<?= Loc::getMessage('SMS4B_MAIN_INTERCEPT_HELP') ?>'>
                                                                    [?]
                                                                </mark>
                                                            </a></td>

                                                        <td valign='top'><input type='checkbox'
                                                                                name='intercept_deadline[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($intercept_deadline === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_TASK_INTERCEPT_DEADLINE'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_NEW_COMM_FROM_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='new_comment_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($new_comment_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_NEW_COMMENT_TASK'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr class='heading'>
                                <td colspan='2'><?= Loc::getMessage('SMS4B_MAIN_TAB_EVENTS_SHOP') ?></td>
                            </tr>
                            <tr>
                                <td align='center' colspan='2'>
                                    <table class='displayEvAdmin'>
                                        <tr>
                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_ADD_TASK') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_LOW_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_add_low_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_add_low_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_TASK_ADD'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_MIDDLE_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_add_middle_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_add_middle_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_TASK_ADD'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_HIGHT_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_add_hight_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_add_hight_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_TASK_ADD'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                </table>
                                            </td>

                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'
                                                            align='center'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_UPDATE_TASK') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_LOW_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_update_low_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_update_low_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_TASK_UPDATE'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_MIDDLE_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_update_middle_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_update_middle_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_TASK_UPDATE'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_HIGHT_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_update_hight_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_update_hight_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_TASK_UPDATE'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                </table>
                                            </td>

                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'
                                                            align='center'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_DELETE_TASK') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_LOW_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_delete_low_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_delete_low_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_TASK_DELETE'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_MIDDLE_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_delete_middle_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_delete_middle_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_TASK_DELETE'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_HIGHT_TASK'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_delete_hight_task[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_delete_hight_task === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_TASK_DELETE'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        <? endif; ?>

                        <? if (IsModuleInstalled('crm')): ?>
                            <? $tabEventsControl->BeginNextTab(); ?>
                            <tr class='heading'>
                                <td align='center' colspan='2'><?= Loc::getMessage('SMS4B_MAIN_TAB_EVENTS_USER') ?></td>
                            </tr>
                            <tr>
                                <td align='center' colspan='2'>
                                    <table class='displayEvAdmin'>
                                        <tr>
                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_LEAD_CRM') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><?= Loc::getMessage('SMS4B_MAIN_ADD_LEAD_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='add_lead_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($add_lead_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADD_LEAD_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><?= Loc::getMessage('SMS4B_MAIN_CHANGE_LEAD_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='update_lead_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($update_lead_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_UPDATE_LEAD_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><?= Loc::getMessage('SMS4B_MAIN_DEL_LEAD_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='delete_lead_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($delete_lead_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_DELETE_LEAD_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr class='heading'>
                                                        <td colspan='3'
                                                            align='center'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_DEAL_CRM') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_ADD_DEAL_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='add_deal_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($add_deal_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADD_DEAL_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_CHANGE_DEAL_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='update_deal_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($update_deal_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_UPDATE_DEAL_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'
                                                            align='center'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_CHANGE_LEAD_STATUS_CRM') ?></td>
                                                    </tr>
                                                    <? foreach (CCrmStatus::GetStatusListEx('STATUS') as $id => $title): ?>
                                                        <tr>
                                                            <td valign='top' align='left'><?= $title; ?></td>
                                                            <td valign='top'><input type='checkbox'
                                                                                    name='change_stat_lead_crm_<?= $id ?>[<?= $siteList[$i]['ID'] ?>]'
                                                                                    value='Y'<?= ($change_stat_lead_crm[$id] === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                            </td>
                                                            <td valign='top'><a
                                                                    href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_CHANGE_STAT_LEAD_CRM_' . $id][0]['ID'] ?>'
                                                                    target='_blank'
                                                                    title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                        src='/bitrix/images/fileman/edit_text.gif'></a>
                                                            </td>
                                                        </tr>
                                                    <? endforeach; ?>
                                                </table>
                                            </td>
                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'
                                                            align='center'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_CHANGE_DEAL_STATUS_CRM') ?></td>
                                                    </tr>
                                                    <? foreach (CCrmStatus::GetStatusListEx('DEAL_STAGE') as $id => $title): ?>
                                                        <tr>
                                                            <td valign='top' align='left'><?= $title; ?></td>
                                                            <td valign='top'><input type='checkbox'
                                                                                    name='change_stat_deal_crm_<?= $id ?>[<?= $siteList[$i]['ID'] ?>]'
                                                                                    value='Y'<?= ($change_stat_deal_crm[$id] === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                            </td>
                                                            <td valign='top'><a
                                                                    href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_CHANGE_STAT_DEAL_CRM_' . $id][0]['ID'] ?>'
                                                                    target='_blank'
                                                                    title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                        src='/bitrix/images/fileman/edit_text.gif'></a>
                                                            </td>
                                                        </tr>
                                                    <? endforeach; ?>
                                                </table>
                                            </td>
                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_CONTACT_CRM') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><?= Loc::getMessage('SMS4B_MAIN_ADD_CONTACT_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='add_contact_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($add_contact_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADD_CONTACT_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><?= Loc::getMessage('SMS4B_MAIN_CHANGE_CONTACT_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='update_contact_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($update_contact_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_UPDATE_CONTACT_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>

                                                    <tr class='heading'>
                                                        <td colspan='3'
                                                            align='center'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_ACTIVITY_CRM') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_REMIND_EVENT_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='remind_event_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($remind_event_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_REMIND_EVENT_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                            <tr class='heading'>
                                <td colspan='2'><?= Loc::getMessage('SMS4B_MAIN_TAB_EVENTS_SHOP') ?></td>
                            </tr>
                            <tr>
                                <td align='center' colspan='2'>
                                    <table class='displayEvAdmin'>
                                        <tr>
                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_LEAD_CRM') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><?= Loc::getMessage('SMS4B_MAIN_ADD_LEAD_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_add_lead_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_add_lead_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_ADD_LEAD_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><?= Loc::getMessage('SMS4B_MAIN_CHANGE_LEAD_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_update_lead_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_update_lead_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_UPDATE_LEAD_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><?= Loc::getMessage('SMS4B_MAIN_DEL_LEAD_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_delete_lead_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_delete_lead_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_DELETE_LEAD_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr class='heading'>
                                                        <td colspan='3'
                                                            align='center'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_DEAL_CRM') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_ADD_DEAL_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_add_deal_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_add_deal_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_ADD_DEAL_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_CHANGE_DEAL_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_update_deal_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_update_deal_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_UPDATE_DEAL_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                </table>
                                            </td>
                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'
                                                            align='center'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_CHANGE_LEAD_STATUS_CRM') ?></td>
                                                    </tr>
                                                    <? foreach (CCrmStatus::GetStatusListEx('STATUS') as $id => $title): ?>
                                                        <tr>
                                                            <td valign='top' align='left'><?= $title; ?></td>
                                                            <td valign='top'><input type='checkbox'
                                                                                    name='admin_change_stat_lead_crm_<?= $id ?>[<?= $siteList[$i]['ID'] ?>]'
                                                                                    value='Y'<?= ($admin_change_stat_lead_crm[$id] === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                            </td>
                                                            <td valign='top'><a
                                                                    href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_CHANGE_STAT_LEAD_CRM_' . $id][0]['ID'] ?>'
                                                                    target='_blank'
                                                                    title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                        src='/bitrix/images/fileman/edit_text.gif'></a>
                                                            </td>
                                                        </tr>
                                                    <? endforeach; ?>
                                                </table>
                                            </td>
                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'
                                                            align='center'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_CHANGE_DEAL_STATUS_CRM') ?></td>
                                                    </tr>
                                                    <? foreach (CCrmStatus::GetStatusListEx('DEAL_STAGE') as $id => $title): ?>
                                                        <tr>
                                                            <td valign='top' align='left'><?= $title; ?></td>
                                                            <td valign='top'><input type='checkbox'
                                                                                    name='admin_change_stat_deal_crm_<?= $id ?>[<?= $siteList[$i]['ID'] ?>]'
                                                                                    value='Y'<?= ($admin_change_stat_deal_crm[$id] === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                            </td>
                                                            <td valign='top'><a
                                                                    href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_CHANGE_STAT_DEAL_CRM_' . $id][0]['ID'] ?>'
                                                                    target='_blank'
                                                                    title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                        src='/bitrix/images/fileman/edit_text.gif'></a>
                                                            </td>
                                                        </tr>
                                                    <? endforeach; ?>
                                                </table>
                                            </td>
                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_CONTACT_CRM') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><?= Loc::getMessage('SMS4B_MAIN_ADD_CONTACT_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_add_contact_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_add_contact_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_ADD_CONTACT_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><?= Loc::getMessage('SMS4B_MAIN_CHANGE_CONTACT_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_update_contact_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_update_contact_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_UPDATE_CONTACT_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>

                                                    <tr class='heading'>
                                                        <td colspan='3'
                                                            align='center'><?= Loc::getMessage('SMS4B_MAIN_TAB_TITLE_ACTIVITY_CRM') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_REMIND_EVENT_CRM'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_remind_event_crm[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_remind_event_crm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_REMIND_EVENT_CRM'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>
                        <? endif; ?>

                        <? if (IsModuleInstalled('voximplant')): ?>
                            <? $tabEventsControl->BeginNextTab(); ?>
                            <tr class='heading'>
                                <td align='center' colspan='2'><?= Loc::getMessage('SMS4B_MAIN_TAB_EVENTS_IM') ?></td>
                            </tr>
                            <tr>
                                <td align='center' colspan='2'>
                                    <table class='displayEvAdmin'>
                                        <tr>
                                            <td valign='top'>
                                                <table width='240px'>
                                                    <tr class='heading'>
                                                        <td colspan='3'><?= Loc::getMessage('SMS4B_MAIN_TITLE_IM') ?></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_OPT_AUTOANSWER'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='event_autoanswer[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($event_autoanswer === 'Y' ? " checked = \"checked\" " : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_AUTOANSWER'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_OPT_MISSED_CALL_NOTIFICATION'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='event_missed_call[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($event_missed_call === 'Y' ? " checked = \"checked\" " : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_MISSED_CALL'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                </table>
                                            </td>
                                        </tr>
                                    </table>
                                </td>
                            </tr>

                        <? endif; ?>

                        <? $tabEventsControl->BeginNextTab(); ?>
                        <tr class='heading'>
                            <td align='center' colspan='2'><?= Loc::getMessage('SMS4B_MAIN_TAB_EVENTS_USER') ?></td>
                        </tr>
                        <tr>
                            <td align='center' colspan='2'>
                                <table class='displayEvAdmin'>
                                    <tr>
                                        <td valign='top'>
                                            <table width='240px'>
                                                <tr class='heading'>
                                                    <td colspan='4'><?= Loc::getMessage('SMS4B_MAIN_TITLE_OTHER') ?></td>
                                                </tr>
                                                <tr>
                                                    <td valign='top'
                                                        align='left'><? echo Loc::getMessage('SMS4B_MAIN_OPT_SUBSCRIBE_CONFIRM'); ?></td>
                                                    <td valign='top'>
                                                        <input type='checkbox'
                                                               name='event_subscribe_confirm[<?= $siteList[$i]['ID'] ?>]'
                                                               value='Y'<?= ($event_subscribe_confirm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                    </td>
                                                    <td valign='top'><a
                                                            href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_SUBSCRIBE_CONFIRM'][0]['ID'] ?>'
                                                            target='_blank'
                                                            title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                </tr>
                                                <? if (IsModuleInstalled('support')): ?>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_OPT_TICKET_NEW_FOR_TECHSUPPORT'); ?></td>
                                                        <td valign='top'>
                                                            <input type='checkbox'
                                                                   name='event_ticket_new_for_techsupport[<?= $siteList[$i]['ID'] ?>]'
                                                                   value='Y'<?= ($event_ticket_new_for_techsupport === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_TICKET_NEW_FOR_TECHSUPPORT'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_TICKET_CHANGE_FOR_TECHSUPPORT'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                <? endif; ?>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr class='heading'>
                            <td colspan='2'><?= Loc::getMessage('SMS4B_MAIN_TAB_EVENTS_SHOP') ?></td>
                        </tr>
                        <tr>
                            <td align='center' colspan='2'>
                                <table class='displayEvAdmin'>
                                    <tr>
                                        <td valign='top'>
                                            <table width='240px'>
                                                <tr class='heading'>
                                                    <td colspan='4'><?= Loc::getMessage('SMS4B_MAIN_TITLE_OTHER') ?></td>
                                                </tr>
                                                <tr>
                                                    <td valign='top'
                                                        align='left'><? echo Loc::getMessage('SMS4B_MAIN_OPT_SUBSCRIBE_CONFIRM'); ?></td>
                                                    <td valign='top'><input type='checkbox'
                                                                            name='admin_event_subscribe_confirm[<?= $siteList[$i]['ID'] ?>]'
                                                                            value='Y'<?= ($admin_event_subscribe_confirm === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                    </td>
                                                    <td valign='top'><a
                                                            href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_SUBSCRIBE_CONFIRM'][0]['ID'] ?>'
                                                            target='_blank'
                                                            title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                </tr>

                                                <? if (IsModuleInstalled('support')): ?>
                                                    <tr>
                                                        <td valign='top'
                                                            align='left'><? echo Loc::getMessage('SMS4B_MAIN_OPT_TICKET_NEW_FOR_TECHSUPPORT'); ?></td>
                                                        <td valign='top'><input type='checkbox'
                                                                                name='admin_event_ticket_new_for_techsupport[<?= $siteList[$i]['ID'] ?>]'
                                                                                value='Y'<?= ($admin_event_ticket_new_for_techsupport === 'Y' ? ' checked = \'checked\' ' : '') ?>/>
                                                        </td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_TICKET_NEW_FOR_TECHSUPPORT'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                        <td valign='top'><a
                                                                href='/bitrix/admin/message_edit.php?ID=<?= $allTemplates['SMS4B_ADMIN_TICKET_CHANGE_FOR_TECHSUPPORT'][0]['ID'] ?>'
                                                                target='_blank'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                                    src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                                    </tr>
                                                <? endif; ?>
                                            </table>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <tr class='heading'>
                            <td colspan='2'><?= Loc::getMessage('SMS4B_MAIN_TAB_CUSTOM_USER_TEMPLATES') ?></td>
                        </tr>
                        <tr>
                            <td align='center' valign='top' colspan='2'>
                                <table width='240px'>
                                    <tr class='heading'>
                                        <td colspan='3'><?= Loc::getMessage('SMS4B_MAIN_CUSTOM_TEMPLATES') ?></td>
                                    </tr>

                                    <script>
                                        $(function () {
                                            $('#createSms4bTemplateButton_<?=$siteList[$i]['ID']?>').click(function () {
                                                BX.ajax({
                                                    method: 'POST',
                                                    url: '/bitrix/admin/sms4b_addTemplate.php',
                                                    data: {
                                                        text: '<?=Loc::getMessage('SMS4B_MAIN_USER_CUSTOM_TEMPLATE_TEXT')?>',
                                                        subject: '<?=Loc::getMessage('SMS4B_MAIN_USER_CUSTOM_TEMPLATE_SUBJECT')?>',
                                                        emailFrom: '<?=Loc::getMessage('SMS4B_MAIN_USER_CUSTOM_TEMPLATE_EMAIL_FROM')?>',
                                                        emailTo: '<?=Loc::getMessage('SMS4B_MAIN_USER_CUSTOM_TEMPLATE_EMAIL_TO')?>',
                                                        eventType: 'USER_LIST_CUSTOM_EVENT',
                                                        site: '<?=$siteList[$i]['ID']?>'
                                                    },
                                                    onsuccess: function (id) {
                                                        if (id > 0) {
                                                            $('.addUserTemplateButton').before('<tr id="customTemplate' + id + '"><td valign="top" align="left"><?=Loc::getMessage('SMS4B_MAIN_USER_CUSTOM_TEMPLATE_TEXT')?></td>' +
                                                                '<td valign="top"><a href="/bitrix/admin/message_edit.php?ID=' + id + '" target="_blank" title="<?=Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK')?>"><img src="/bitrix/images/fileman/edit_text.gif"></a></td>' +
                                                                '<td valign="top"><a href="javascript:void(0);" id=\'' + id + '\' class=\'deleteSms4bTemplateButton\' title="<?=Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_DEL_LINK')?>"><img src="/bitrix/images/main/del.gif"></a></td></tr>'
                                                            );
                                                        }
                                                    }
                                                })
                                            })

                                            $('body').on('click', '.deleteSms4bTemplateButton', function () {
                                                var id = $(this).get(0).id;

                                                BX.ajax({
                                                    method: 'POST',
                                                    url: '/bitrix/admin/sms4b_delTemplate.php',
                                                    data: {templateId: id},
                                                    onsuccess: function () {
                                                        $("#customTemplate" + id).remove();
                                                    }
                                                })
                                            })
                                        })
                                    </script>
                                    <? foreach ((array)$customUserTemplates['SMS4B_USER_LIST_CUSTOM_EVENT'] as $val): ?>
                                        <tr id='customTemplate<?= $val['ID'] ?>'>
                                            <td valign='top' align='left'><?= $val['NAME']; ?></td>
                                            <td valign='top'><a
                                                    href='/bitrix/admin/message_edit.php?ID=<?= $val['ID'] ?>'
                                                    target='_blank'
                                                    title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_LINK') ?>'><img
                                                        src='/bitrix/images/fileman/edit_text.gif'></a></td>
                                            <td valign='top'><a href='javascript:void(0);' id='<?= $val['ID'] ?>'
                                                                class='deleteSms4bTemplateButton'
                                                                title='<?= Loc::getMessage('SMS4B_MAIN_TITLE_HANDLER_DEL_LINK') ?>'><img
                                                        src='/bitrix/images/main/del.gif'></a></td>
                                        </tr>
                                    <? endforeach; ?>

                                    <tr class='addUserTemplateButton'>
                                        <td align='center' colspan='3'>
                                            <a id='createSms4bTemplateButton_<?=$siteList[$i]['ID']?>' hidefocus='true'
                                               class='adm-btn'><?= Loc::getMessage('SMS4B_MAIN_ADD_CUSTOM_TEMPLATE') ?></a>
                                        </td>
                                    </tr>
                                </table>
                            </td>
                        </tr>

                        <? $tabEventsControl->End(); ?>
                    </table>
                    <?
                endfor;
                $tabControl3->End();
                ?>
            </td>
        </tr><?
        //sms event
        $tabControl->BeginNextTab(); ?>
        <?
        //now only ro russia
        $arFilter = array(
            'LID' => 'ru'
        );
        $obEvents = CEventType::GetList($arFilter);
        while ($arEvent = $obEvents->Fetch()) {
            if (strstr($arEvent['EVENT_NAME'], 'SMS4B')  //all events sms4b
                //skip events that are already customised by module
                || strstr($arEvent['EVENT_NAME'], 'SALE_STATUS_CHANGED')
                || strstr($arEvent['EVENT_NAME'], 'SUBSCRIBE_CONFIRM')
                || strstr($arEvent['EVENT_NAME'], 'SALE_ORDER_PAID')
                || strstr($arEvent['EVENT_NAME'], 'SALE_ORDER_DELIVERY')
                || strstr($arEvent['EVENT_NAME'], 'SALE_ORDER_CANCEL')
                || strstr($arEvent['EVENT_NAME'], 'SALE_NEW_ORDER')
                || strstr($arEvent['EVENT_NAME'], 'TICKET_NEW_FOR_TECHSUPPORT')
                || strstr($arEvent['EVENT_NAME'], 'TICKET_CHANGE_FOR_TECHSUPPORT')
            ) {
                $eventTypes[] = $arEvent['EVENT_NAME'];
                if (strstr($arEvent['EVENT_NAME'], 'SMS4B')) {
                    $sms4bEvents[] = $arEvent['EVENT_NAME'];
                }
            } else {
                $arEvents[] = $arEvent;
            }
        }
        /* Find all events*/
        foreach ($siteList as $val) {
            $arFilter = Array(
                'SITE_ID' => $val['ID'],
                'ACTIVE' => 'Y',
            );
            $dbMess = CEventMessage::GetList($by = 'site_id', $order = 'desc', $arFilter);
            while ($arMessage = $dbMess->Fetch()) {
                $arTemplateEvent[$val['ID']][] = $arMessage['EVENT_NAME'];
            }
            $arTemplateEvent[$val['ID']] = array_unique($arTemplateEvent[$val['ID']]);
        }

        ?>
        <tr>
            <td>
                <?
                $aTabs2 = Array();
                foreach ($siteList as $val) {
                    $aTabs2[] = Array(
                        'DIV' => 'template' . $val['ID'],
                        'TAB' => '[' . $val['ID'] . '] ' . $val['NAME'],
                        'TITLE' => Loc::getMessage('SMS4B_MAIN_TAB_TITLE_EMAIL_EVENTS') . ' [' . $val['ID'] . '] ' . $val['NAME']
                    );
                }
                $tabControl2 = new CAdminViewTabControl('tabControl2', $aTabs2);
                $tabControl2->Begin();

                foreach ($siteList as $val) {
                    $tabControl2->BeginNextTab();

                    ?>
                    <div class='site' data-site='<?= $val['ID'] ?>'>
                        <table class='display' width='100%'>
                            <thead align='left'>
                            <th width='5%'><?= Loc::getMessage('SMS4B_MAIN_TABLE_EMAIL_SMS') ?></th>
                            <th width='30%'><?= Loc::getMessage('SMS4B_MAIN_TABLE_EMAIL_TYPE') ?></th>
                            <th><?= Loc::getMessage('SMS4B_MAIN_TABLE_EMAIL_NAME') ?></th>
                            </thead>
                            <? foreach ($arEvents as $event): ?>
                                <tr class='gradeU'>
                                    <td align='center'>
                                        <? if (in_array('SMS4B_' . $event['EVENT_NAME'],
                                            $arTemplateEvent[$val['ID']])): ?>
                                            <img src='/bitrix/images/workflow/green.gif' width='14' height='14'
                                                 border='0' alt='<?= Loc::getMessage('SMS4B_MAIN_TABLE_EMAIL_SMS_EXISTS') ?>'
                                                 title='<?= Loc::getMessage('SMS4B_MAIN_TABLE_EMAIL_SMS_EXISTS') ?>'>
                                            <?
                                        else: ?>
                                        <? endif; ?>
                                    </td>
                                    <td><a href='#' class='click' data-event='<?= $event['ID'] ?>'
                                           title='<?= Loc::getMessage('SMS4B_MAIN_TABLE_EMAIL_SMS_CLICK') ?>'><?= $event['EVENT_NAME'] ?></a>
                                    </td>
                                    <td><a href='#' class='click' data-event='<?= $event['ID'] ?>'
                                           title='<?= Loc::getMessage('SMS4B_MAIN_TABLE_EMAIL_SMS_CLICK') ?>'><?= $event['NAME'] ?></a>
                                    </td>
                                </tr>
                            <? endforeach; ?>
                        </table>
                    </div>
                <? } ?>

            </td>
        </tr>
        <?
        $tabControl2->End();
        ?>
        <div id='ajax-add-answer'></div>
        <script>
            $(document).ready(function () {
                $('.display').dataTable({
                    'bPaginate': false,
                    'bLengthChange': false,
                    'bFilter': true,
                    'bSort': true,
                    'aaSorting': [[1, 'asc']],
                    'bInfo': false,
                    'bAutoWidth': false,
                    'oLanguage': {
                        'sZeroRecords': '<?=Loc::getMessage('SMS4B_MAIN_TABLE_EMAIL_NO_ELEMENTS')?>',
                        'sSearch': '<?=Loc::getMessage('SMS4B_MAIN_TABLE_EMAIL_SEARCH')?>'
                    }
                });
                //@todo todo window modal and center it
                $('.click').click(function (e) {
                    var windowId = $(this).data('event');
                    e.preventDefault();
                    var addTemplate = '';
                    var index = BX.PopupWindowManager._getPopupIndex(windowId);
                    //we work with manager
                    //we do so, because all pop-us have common div#ajax-add-answer
                    //another way to create for each pop-up its own div
                    if (index >= 0) {
                        addTemplate = BX.PopupWindowManager._popups[index];
                        $('#ajax-add-answer').remove();
                        addTemplate.setContent(BX.create('div', {'props': {'id': 'ajax-add-answer'}}));
                    }
                    else {
                        var addTemplate = BX.PopupWindowManager.create(windowId, this, {
                            content: BX('ajax-add-answer'),
                            closeIcon: {right: '20px', top: '10px'},
                            titleBar: {
                                content: BX.create('span', {
                                    html: '<b><?=Loc::getMessage('SMS4B_MAIN_TABLE_EMAIL_POPUP_TITLE')?></b>',
                                    'props': {'className': 'access-title-bar'}
                                })
                            },
                            zIndex: 0,
                            offsetLeft: 0,
                            offsetTop: 0,
                            draggable: {restrict: true},
                            buttons: [
                                new BX.PopupWindowButton({
                                    text: '<?=Loc::getMessage('SMS4B_MAIN_TABLE_EMAIL_SAVE')?>',
                                    className: 'popup-window-button-accept',
                                    id: 'sms4b-popup-window-button-accept',
                                    events: {
                                        click: function () {
                                            BX.ajax.submit(BX('myForm'), function (data) {
                                                BX('ajax-add-answer').innerHTML = data;
                                            });
                                            $('#sms4b-popup-window-button-accept').remove();
                                        }
                                    }
                                }),
                                new BX.PopupWindowButton({
                                    text: '<?=Loc::getMessage('SMS4B_MAIN_TABLE_EMAIL_CLOSE')?>',
                                    className: 'webform-button-link-cancel',
                                    events: {
                                        click: function () {
                                            this.popupWindow.close();
                                        }
                                    }
                                })
                            ]
                        });
                    }
                    var addlink = '/bitrix/admin/sms4b_main_addtemplate.php?eventID=' + $(this).data('event') + '&site=' + $(this).parents('div.site').data('site');
                    BX.ajax.insertToNode(addlink, BX('ajax-add-answer'));
                    addTemplate.show();
                });
            });
        </script>
        <? $tabControl->BeginNextTab(); ?>
        <? echo Loc::getMessage('SMS4B_MAIN_HELP'); ?>

        <? $tabControl->BeginNextTab(); ?>
        <? if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['submit_button'] && $_REQUEST['ticket_text']) {
            $info = CModule::CreateModuleObject($moduleId);

            $text = $_REQUEST['ticket_text'] . PHP_EOL . PHP_EOL;

            $text .= Loc::getMessage('SMS4B_MAIN_SERVER') . ': ' . $_SERVER['HTTP_HOST'] . PHP_EOL .
                Loc::getMessage('SMS4B_MAIN_SENDER') . ': ' . $_REQUEST['email'] . PHP_EOL .
                Loc::getMessage('SMS4B_MAIN_MODULE_ID') . ': ' . $moduleId . PHP_EOL .
                Loc::getMessage('SMS4B_MAIN_VERSION') . ': ' . $info->MODULE_VERSION . PHP_EOL .
                Loc::getMessage('SMS4B_MAIN_INFO_PRODUCT') . ': ' . Loc::getMessage('SMS4B_MAIN_INFO_PRODUCT_NAME_'
                    . COption::GetOptionString('main', 'vendor', '1c_bitrix')) . PHP_EOL .
                Loc::getMessage('SMS4B_MAIN_LOGIN') . ': ' . COption::GetOptionString($moduleId, 'login') . PHP_EOL;

            if (mail('info@sms4b.ru', Loc::getMessage('SMS4B_MAIN_EMAIL_SUBJECT') . $_SERVER['HTTP_HOST'], $text)) {
                $message = new CAdminMessage(array(
                    'MESSAGE' => Loc::getMessage('SMS4B_MAIN_SEND_MAIL_TO_SUPPORT_SUCCESS'),
                    'TYPE' => 'OK',
                    'HTML' => true
                ));
            } else {
                $message = new CAdminMessage(array(
                    'MESSAGE' => Loc::getMessage('SMS4B_MAIN_SEND_MAIL_TO_SUPPORT_FAIL'),
                    'TYPE' => 'ERROR',
                    'HTML' => true
                ));
            }
            echo $message->Show();
        }

        ?>
        <tr>
            <td align='right' width='30%'><span class='required'>*</span><?= Loc::getMessage('SMS4B_MAIN_NAME') ?></td>
            <td><input type='text' name='fio'/></td>
        </tr>
        <tr>
            <td align='right' width='30%'><span class='required'>*</span><?= Loc::getMessage('SMS4B_MAIN_EMAIL') ?></td>
            <td><input type='text' name='email' value='<?= COption::GetOptionString('main', 'email_from') ?>'/></td>
        </tr>
        <tr>
            <td align='right' width='30%'><span class='required'>*</span><?= Loc::getMessage('SMS4B_MAIN_ABOUT') ?><br>
                <small><?= Loc::getMessage('SMS4B_MAIN_ERROR') ?></small>
            </td>
            <td><textarea name='ticket_text' rows='6' cols='60'></textarea></td>
        </tr>
        <tr>
            <td></td>
            <td><input type='submit' name='submit_button' value='<?= Loc::getMessage('SMS4B_MAIN_SUBMIT'); ?>'></td>
        </tr>

        <? $tabControl->BeginNextTab(); ?>
        <? require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/admin/group_rights.php'); ?>
        <?
        if ($REQUEST_METHOD === 'POST' && strlen($Update . $RestoreDefaults) > 0 && check_bitrix_sessid()) {
            if (strlen($Update) > 0 && strlen($_REQUEST['back_url_settings']) > 0) {
                LocalRedirect($_REQUEST['back_url_settings']);
            } else {
                LocalRedirect($APPLICATION->GetCurPage() . '?mid=' . urlencode($mid) . '&lang=' . urlencode(LANGUAGE_ID) . '&back_url_settings=' . urlencode($_REQUEST['back_url_settings']) . '&' . $tabControl->ActiveTabParam());
            }
        }
        ?>

        <? $tabControl->BeginNextTab(); ?>
        <? if ($REQUEST_METHOD === 'POST' && strlen($_REQUEST['clean_log']) > 0 && check_bitrix_sessid()) {
            $SMS4B->clLogFile();
        }

        if ($log_enable === 'Y') {
            $message = new CAdminMessage(array(
                'MESSAGE' => Loc::getMessage('SMS4B_MAIN_LOG_ENABLE'),
                'TYPE' => 'OK',
                'HTML' => true
            ));
        } else {
            $message = new CAdminMessage(array(
                'MESSAGE' => Loc::getMessage('SMS4B_MAIN_LOG_DISABLE'),
                'TYPE' => 'ERROR',
                'HTML' => true
            ));
        }
        echo $message->Show();
        ?>

        <p><textarea id='logData' name='logData' cols='200' rows='40'><?= $SMS4B->getLogData(); ?></textarea></p>
        <p><input type='checkbox' name='log_enable'
                  value='Y' <?= ($log_enable === 'Y' ? 'checked' : '') ?>><? echo Loc::getMessage('SMS4B_MAIN_TAB_ENABLE_LOG'); ?>
            <br>
        <p>
            <input type='submit' name='clean_log' value='<?= Loc::getMessage('SMS4B_MAIN_CLEAN_LOG') ?>'
                   title='<?= Loc::getMessage('SMS4B_MAIN_CLEAN_LOG_DESC') ?>'>
            <input type='submit' name='update_log' value='<?= Loc::getMessage('SMS4B_MAIN_UP_LOG') ?>'
                   title='<?= Loc::getMessage('SMS4B_MAIN_UP_LOG_DESC') ?>'>
        </p>

        <? $tabControl->Buttons(); ?>
        <input <? if ($groupRight < 'W') echo 'disabled' ?> type='submit' name='Update'
                                                            value='<?= GetMessage('MAIN_SAVE') ?>'
                                                            title='<?= GetMessage('MAIN_OPT_SAVE_TITLE') ?>'>
        <? if (strlen($_REQUEST['back_url_settings']) > 0): ?>
            <input type='button' name='Cancel' value='<?= GetMessage('MAIN_OPT_CANCEL') ?>'
                   title='<?= GetMessage('MAIN_OPT_CANCEL_TITLE') ?>'
                   onclick='window.location='<?= htmlspecialchars(CUtil::addslashes($_REQUEST['back_url_settings'])) ?>''>
            <input type='hidden' name='back_url_settings'
                   value='<?= htmlspecialchars($_REQUEST['back_url_settings']) ?>'>
        <? endif ?>
        <input <? if ($groupRight < 'W') echo 'disabled' ?> type='submit' name='RestoreDefaults'
                                                            title='<?= GetMessage('MAIN_HINT_RESTORE_DEFAULTS') ?>'
                                                            OnClick='confirm('<?= AddSlashes(GetMessage('MAIN_HINT_RESTORE_DEFAULTS_WARNING')) ?>
        ')'
        value='<?= Loc::getMessage('SMS4B_MAIN_RESTORE_DEFAULTS') ?>'>
        <?= bitrix_sessid_post(); ?>
        <? $tabControl->End(); ?>

    </form>
    <?
else:?>
    <?= CAdminMessage::ShowMessage(Loc::getMessage('SMS4B_MAIN_NO_RIGHTS_FOR_VIEWING')); ?>
<?endif;
?>