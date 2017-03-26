<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use \Bitrix\Main\Localization\Loc;
Loc::loadLanguageFile(__FILE__);

$arActivityDescription = array(
    'NAME' => Loc::getMessage('SMS4B_MAIN_BPMA_DESCR_NAME'),
    'DESCRIPTION' => Loc::getMessage('SMS4B_MAIN_BPMA_DESCR_DESCR'),
    'TYPE' => 'activity',
    'CLASS' => 'SMS4BActivityToLead',
    'JSCLASS' => 'BizProcActivity',
    'CATEGORY' => array(
        'ID' => 'rest',
        'OWN_ID' => 'sms4b',
        'OWN_NAME' => Loc::getMessage('SMS4B_MAIN_BPMA_CATEGORY_NAME')
    )
);
