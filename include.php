<?
\Bitrix\Main\Loader::registerAutoLoadClasses(
    'rarus.sms4b',
    array(
        '\Rarus\Sms4b\Sms4bTable' => 'lib/sms4b.php',
        'Rarus\Sms4b\Sms4bTable' => 'lib/sms4b.php',
        '\Rarus\Sms4b\Sms4bIncTable' => 'lib/sms4bInc.php',
        'Rarus\Sms4b\Sms4bIncTable' => 'lib/sms4bInc.php',
		'\Rarus\Sms4b\Sms4bException' => 'lib/Exception.php',
		'Rarus\Sms4b\Sms4bException' => 'lib/Exception.php',
    	'\Rarus\Sms4b\Sms4bClient' => 'lib/Client.php',
    	'Rarus\Sms4b\Sms4bClient' => 'lib/Client.php'
    )
);

global $DB;
IncludeModuleLangFile(__FILE__);
require_once($_SERVER['DOCUMENT_ROOT']. '/bitrix/modules/rarus.sms4b/classes/' .strtolower($DB->type). '/sms4b.php');
