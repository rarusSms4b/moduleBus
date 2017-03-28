<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED!==true) die();

use \Bitrix\Main\Localization\Loc;
Loc::loadLanguageFile(__FILE__);

$arActivityDescription = array(
	'NAME' => Loc::getMessage('SMS4B_ROBOT_NAME'),
	'DESCRIPTION' => Loc::getMessage('SMS4B_ROBOT_DESC'),
	'TYPE' => array('activity', 'robot_activity'),
	'CLASS' => 'Sms4bRobotSendSms',
	'JSCLASS' => 'BizProcActivity',
    'CATEGORY' => array(
        'ID' => 'rest',
        'OWN_ID' => 'sms4b',
        'OWN_NAME' => Loc::getMessage('SMS4B_ROBOT_CATEGORY_NAME'),
    ),
	'FILTER' => array(
		'INCLUDE' => array(
			array('crm', 'CCrmDocumentDeal'),
			array('crm', 'CCrmDocumentLead')
		),
	),
	'ROBOT_SETTINGS' => array(
		'IS_AUTO' => true
	),
);