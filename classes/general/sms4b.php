<?
require_once('CSms4bBase.php');

use Rarus\Sms4b;
use Rarus\Sms4b\Sms4bException;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;
use Bitrix\Main\EventManager;
use Bitrix\Main\Mail;
use    Bitrix\Sale\Internals\StatusTable;
use Bitrix\Sale\Internals\StatusLangTable;
use Bitrix\Sale\Internals\ShipmentTable;

Loc::loadLanguageFile(__FILE__);

/**
 * @author AZAREV
 * @version 1.2.0
 */
class CSms4BitrixWrapper extends Csms4bBase
{
    /**
     * @const SERVICE_DATE_TIME_FORMAT - формат даты для передачи данных сервису
     */
    const SERVICE_DATE_TIME_FORMAT = 'Y-m-d H:i:s';
    /**
     * @const DEFAULT_ACTUAL_DAYS - количество дней по-умолчанию, в течении которых актуальна отправка
     */
    const DEFAULT_ACTUAL_DAYS = 8;
    /**
     * @const RESULT_ON_ORDER_LIST - количество результатов, выводимых на странице заказов
     */
    const RESULT_ON_ORDER_LIST = 3;
    /**
     * @var integer Статус СМС доставлено
     */
    const DELIVERED = 1;
    /**
     * @var integer Статус СМС В отправке
     */
    const INPROCESS = 2;
    /**
     * @var integer Статус СМС не доставлено
     */
    const NOTDELIVERED = 3;
    /**
     * @var integer Таймер (мин) для проверки подключения и отправки письма
     */
    const TIMER_ERROR_MAIL_SEND = 5;

    public $proxy_port;
    public $proxy_host;
    public $user_check;
    public $uid;

    /**
     * Объявление свойств класса
     */
    public function __construct()
    {
        $info = CModule::CreateModuleObject('rarus.sms4b');

        $this->login = ' b' . $info->MODULE_VERSION . ' ' . COption::GetOptionString('rarus.sms4b', 'login');
        $this->password = htmlspecialchars(COption::GetOptionString('rarus.sms4b', 'password'));
        $this->gmt = COption::GetOptionString('rarus.sms4b', 'gmt');

        $this->serv_addr = 'https://sms4b.ru';
        $this->serv_port = COption::GetOptionString('rarus.sms4b', 'port');

        $this->proxy_host = COption::GetOptionString('rarus.sms4b', 'proxy_host');
        $this->proxy_port = COption::GetOptionString('rarus.sms4b', 'proxy_port');
        $this->proxy_use = COption::GetOptionString('rarus.sms4b', 'proxy_use');

        $this->inc_date = COption::GetOptionString('rarus.sms4b', 'inc_date');

        $this->UpdateSID();

        //now check if default number is correct
        $domain = str_replace('www.', '', $_SERVER['HTTP_HOST']);

        $rsSites = CSite::GetList(
            $by = 'sort',
            $order = 'desc',
            Array('DOMAIN' => $domain)
        );
        if ($arSite = $rsSites->Fetch()) {
            //different domains
            $site = $arSite['ID'];
            $this->DefSender = COption::GetOptionString('rarus.sms4b', 'defsender', false, $site);
            $this->use_translit = COption::GetOptionString('rarus.sms4b', 'use_translit', false, $site);
        } else {
            //one domain
            $siteUrl = $_SERVER['SERVER_NAME'];
            $rsSites = CSite::GetList(
                $by = 'sort',
                $order = 'desc',
                Array('SERVER_NAME' => $siteUrl)
            );
            if ($arSite = $rsSites->Fetch()) {
                $site = $arSite['ID'];
                $this->DefSender = COption::GetOptionString('rarus.sms4b', 'defsender', false, $site);
                $this->use_translit = COption::GetOptionString('rarus.sms4b', 'use_translit', false, $site);
            }
        }
    }

    /**
     * Формирует тело xml запроса
     *
     * @param $funcname string - имя функции
     * @param $param array - параметры для функции
     * @param $nameclient string - идентификатор клиента
     *
     * @return string - тело xml запроса
     */
    protected function getbodyrec($funcname = '', $param = array(), $nameclient)
    {
        $bodyrec = '<' . $funcname . ' xmlns="SMS ' . $nameclient . '">' . "\r\n";

        foreach ($param as $name => $val) {
            if ($funcname === 'SaveMessages' && $name === 'List') {
                $head_schema = <<<'EOT'
<List>
<xsd:schema id="NewDataSet" xmlns="" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:msdata="urn:schemas-microsoft-com:xml-msdata">
<xsd:element name="NewDataSet" msdata:IsDataSet="true" msdata:UseCurrentLocale="true">
<xsd:complexType>
<xsd:choice minOccurs="0" maxOccurs="unbounded">
<xsd:element name="Table1">
<xsd:complexType>
<xsd:sequence>
<xsd:element name="SessionID" type="xsd:int" minOccurs="0" />
<xsd:element name="guid" type="xsd:string" minOccurs="0" />
<xsd:element name="StartUp" type="xsd:string" minOccurs="0" />
<xsd:element name="Period" type="xsd:string" minOccurs="0" />
<xsd:element name="Destination" type="xsd:string" minOccurs="0" />
<xsd:element name="Source" type="xsd:string" minOccurs="0" />
<xsd:element name="Body" type="xsd:string" minOccurs="0" />
<xsd:element name="Encoded" type="xsd:unsignedByte" minOccurs="0" />
<xsd:element name="dton" type="xsd:unsignedByte" minOccurs="0" />
<xsd:element name="dnpi" type="xsd:unsignedByte" minOccurs="0" />
<xsd:element name="ston" type="xsd:unsignedByte" minOccurs="0" />
<xsd:element name="snpi" type="xsd:unsignedByte" minOccurs="0" />
<xsd:element name="TimeOff" type="xsd:string" minOccurs="0" />
<xsd:element name="Priority" type="xsd:unsignedByte" minOccurs="0" />
<xsd:element name="NoRequest" type="xsd:string" minOccurs="0" />
</xsd:sequence>
</xsd:complexType>
</xsd:element>
</xsd:choice>
</xsd:complexType>
</xsd:element>
</xsd:schema>
<diffgr:diffgram xmlns:msdata="urn:schemas-microsoft-com:xml-msdata" xmlns:diffgr="urn:schemas-microsoft-com:xml-diffgram-v1">
<NewDataSet xmlns="">

EOT;
                $sms = $val;

                $i = 1;
                $bodyrec .= $head_schema;
                foreach ($sms as $key => $value) {
                    $bodyrec .= '<Table1 diffgr:id="Table1%table_num%" msdata:rowOrder="0" diffgr:hasChanges="inserted">' . "\r\n";
                    $bodyrec = str_replace('%table_num%', $i, $bodyrec);
                    $i++;
                    foreach ($value as $xml_tag_name => $xml_tag_value) {
                        $bodyrec .= '<' . $xml_tag_name . '>' . $xml_tag_value . '</' . $xml_tag_name . '>' . "\r\n";
                    }
                    $bodyrec .= '</Table1>' . "\r\n";
                }

                $bodyrec .= '</NewDataSet>' . "\r\n";
                $bodyrec .= '</diffgr:diffgram>' . "\r\n";
                $bodyrec .= '</List>' . "\r\n";
            } else {
                if ($funcname === 'SaveGroup' && $name === 'List') {
                    $head_schema = <<<'EOT'
<List>
<xsd:schema id="NewDataSet" xmlns="" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:msdata="urn:schemas-microsoft-com:xml-msdata">
<xsd:element name="NewDataSet" msdata:IsDataSet="true" msdata:UseCurrentLocale="true">
	<xsd:complexType>
	<xsd:choice minOccurs="0" maxOccurs="unbounded">
	<xsd:element name="Table1">
	<xsd:complexType>
		<xsd:sequence>
		<xsd:element name="G" type="xsd:string" minOccurs="0" />
		<xsd:element name="D" type="xsd:string" minOccurs="0" />
		<xsd:element name="T" type="xsd:int" minOccurs="0" />
		<xsd:element name="N" type="xsd:int" minOccurs="0" />
		<xsd:element name="E" type="xsd:int" minOccurs="0" />
		<xsd:element name="B" type="xsd:string" minOccurs="0" />
		</xsd:sequence>
	</xsd:complexType>
	</xsd:element>
	</xsd:choice>
	</xsd:complexType>
</xsd:element>
</xsd:schema>
<diffgr:diffgram xmlns:msdata="urn:schemas-microsoft-com:xml-msdata" xmlns:diffgr="urn:schemas-microsoft-com:xml-diffgram-v1">
	<NewDataSet xmlns="">

EOT;
                    $sms = $val;

                    $i = 1;
                    $bodyrec .= $head_schema;
                    foreach ($sms as $key => $value) {
                        $bodyrec .= '<Table1 diffgr:id="Table1%table_num%" msdata:rowOrder="0" diffgr:hasChanges="inserted">' . "\r\n";
                        $bodyrec = str_replace('%table_num%', $i, $bodyrec);
                        $i++;
                        foreach ($value as $xml_tag_name => $xml_tag_value) {
                            $bodyrec .= '<' . $xml_tag_name . '>' . $xml_tag_value . '</' . $xml_tag_name . '>' . "\r\n";
                        }
                        $bodyrec .= '</Table1>' . "\r\n";
                    }

                    $bodyrec .= '</NewDataSet>' . "\r\n";
                    $bodyrec .= '</diffgr:diffgram>' . "\r\n";
                    $bodyrec .= '</List>' . "\r\n";
                } else {
                    $bodyrec .= '<' . $name . '>' . $val . '</' . $name . '>' . "\r\n";
                }
            }
        }
        $bodyrec .= '</' . $funcname . '>' . "\r\n";
        $bodyrec = $this->xml_header . $bodyrec . $this->xml_footer;
        return $bodyrec;
    }

    /**
     * Делает запрос к сервису и возвращает распарсенный ответ
     *
     * @param $funcname string - имя функции
     * @param $param array - параметры для функции
     *
     * @return mixed - распарсенный ответ
     */
    public function GetSOAP($funcname = '', $param = array())
    {
        $this->LastError = '';
        $response = $this->makeRequest($funcname, $param, $this->defClient, $this->defAddr);
        if ($response != -1 && $response !== false) {
            switch ($funcname) {
                case 'StartSession':
                    $this->sid = $this->StartSession($response);

                    if (!$this->sid < 0) {
                        $this->LastError = Loc::getMessage('SMS4B_MAIN_SESSION_UNKNOWN');
                        return false;
                    } elseif ($this->sid === 0) {
                        $this->LastError = Loc::getMessage('SMS4B_MAIN_SESSION_LOST');
                        return false;
                    } else {
                        COption::SetOptionString('rarus.sms4b', 'sid', $this->sid);
                        $this->LastError = '';
                        return true;
                    }
                    break;

                case 'LoadMessage':
                    $result = $this->LoadMessage($response);

                    if ($result['Result'] < 0) {
                        $this->LastError = Loc::getMessage('SMS4B_MAIN_ERROR_LOADMESSAGE');
                        return -1;
                    } elseif ($result['Result'] === 0) {
                        return 0;
                    } else {
                        return $result;
                    }
                    break;

                case 'LoadResponse':
                    $result = $this->LoadResponse($response);
                    return $result;
                    break;

                case 'CloseSession':
                    if ($this->GetSID() > 1) {
                        $closeid = $this->CloseSession($response);
                        if ($closeid > 0) {
                            $this->LastError = '';
                            return true;
                        } elseif ($closeid === 0) {
                            $this->LastError = '';
                            return true;
                        } else {
                            $this->LastError = Loc::getMessage('SMS4B_MAIN_SESSION_CLOSE') . $closeid;
                            return false;
                        }
                    } else {
                        $this->LastError = Loc::getMessage('SMS4B_MAIN_NO_SESSION');
                        return false;
                    }
                    break;

                case 'ParamSMS':
                    if ($this->AccountParams($response)) {

                        return true;
                    } else {
                        return false;
                    }
                    break;

                case 'AccountParams':
                    if ($this->AccountParams($response)) {
                        COption::SetOptionString('rarus.sms4b', 'error_send_letter', '');
                        return true;
                    } else {
                        return false;
                    }
                    break;

                case 'SaveMessage':

                    $saveMessageResult = $this->SaveMessage($response);

                    if ($saveMessageResult > 0) {
                        $ok = (int)$saveMessageResult;
                        $arrSaveres['SEND'] = 255 & $ok;
                        $arrSaveres['OK'] = 255 & ($ok >> 8);

                        return $arrSaveres;
                    } else {
                        $this->AnalyzeResultSaveMessage($saveMessageResult);
                        return false;
                    }

                    break;

                case 'SaveMessages':
                    return $this->SaveMessages($response);

                    break;
                case 'SaveGroup':
                    return $this->SaveGroup($response);
                    break;

                case 'CheckUser':
                    $res = $this->CheckUser($response);
                    if ($res >= 0) {
                        $this->LastError = '';
                        $this->user_check = true;
                        return true;
                    } else {
                        $this->LastError = Loc::getMessage('SMS4B_MAIN_ACSESS_DENY');
                        $this->user_check = false;
                        return false;
                    }
                    break;

                case 'ChangePassword':

                    $res = $this->ChangePassword($response);

                    if ($res > 0) {
                        $this->LastError = '';
                        $old_pass = $this->password;
                        $this->password = $param['NewPassword'];
                        $this->user_check = true;

                        $sms4b_db->Update(array(
                            'ID' => $this->uid,
                            'Login' => $this->login,
                            'Password' => $this->password,
                            'OldPassword' => $old_pass
                        ));
                        return true;
                    } else {
                        $this->LastError = Loc::getMessage('SMS4B_MAIN_PASS_CHANGE');
                        $this->user_check = false;
                        return false;
                    }
                    break;

                case 'ChangeUserPassword':
                    $res = $this->ChangeUserPassword($response);
                    if ($res > 0) {
                        $this->LastError = '';
                        return true;
                    } else {
                        $this->LastError = Loc::getMessage('SMS4B_MAIN_PASS_USER_CHANGE');
                        return false;
                    }
                    break;


                case 'LoadOutReports':
                    return $this->LoadOutReports($response);
                    break;

                case 'LoadIn':

                    $LoadInResult = $this->LoadIn($response);
                    if (count($LoadInResult) > 0) {
                        return $LoadInResult;
                    } else {
                        return false;
                    }
                    break;

                default: {
                    $this->LastError = Loc::getMessage('SMS4B_MAIN_FUNCTION_UNKNOWN');
                    return false;
                }
            }
        } else {
            $this->LastError = Loc::getMessage('SMS4B_MAIN_CONNECTION_LOST');
            return false;
        }
    }

    /**
     * Парсинг xml-ответа на запрос LoadIn
     *
     * @param $xml string - xml-ответ
     *
     * @return array - массив с результатом загрузки входящих сообщений
     */
    protected function LoadIn($xml)
    {
        $param_array =
            array('GUID', 'Moment', 'TimeOff', 'Source', 'Destination', 'Coding', 'Body', 'Total', 'Part');

        $resultArray = $this->ParserTableResp($xml, $param_array);
        if (count($resultArray[0]) < 8) {
            return 0;
        } else {
            return $resultArray;
        }
    }

    /**
     * Парсинг xml-ответа на запрос CheckUser
     *
     * @param $xml string - xml-ответ
     *
     * @return mixed - SID
     */
    protected function CheckUser($xml)
    {
        $sid = false;
        $xml = str_replace(array("\r\n", "\n"), '', $xml);
        preg_match("/<CheckUserResult>([\-0-9]+?)<\/CheckUserResult>/", $xml, $find);
        if (is_numeric($find[1])) {
            $sid = (int)$find[1];
        }
        return $sid;
    }

    /**
     * Парсинг xml-ответа на запрос ChangePassword
     *
     * @param $xml string - xml-ответ
     *
     * @return string - SID
     */
    protected function ChangePassword($xml)
    {
        $xml = str_replace(array("\r\n", "\n"), '', $xml);
        preg_match("/<ChangePasswordResult>([\-0-9]+?)<\/ChangePasswordResult>/", $xml, $find);
        return (int)$find[1];
    }

    /**
     * Парсинг xml-ответа на запрос ChangeUserPassword
     *
     * @param $xml string - xml-ответ
     *
     * @return string - SID
     */
    protected function ChangeUserPassword($xml)
    {
        $xml = str_replace(array("\r\n", "\n"), '', $xml);
        preg_match("/<ChangeUserPasswordResult>([\-0-9]+?)<\/ChangeUserPasswordResult>/", $xml, $find);
        return (int)$find[1];
    }

    /**
     * Парсинг xml-ответа на запрос LoadOutReports
     *
     * @param $xml string - xml-ответ
     *
     * @return array - распарсенный ответ сервиса
     */
    protected function LoadOutReports($xml)
    {
        $arReports = array();
        $xml = str_replace(array("\r\n", "\n"), '', $xml);
        $this->LastError = '';

        preg_match_all("/<Table.+?>(.+?)<\/Table>/i", $xml, $find);

        foreach ($find[1] as $key => $val) {
            $arReports[] = $this->ParserResp($val, array(
                    'SenderName',
                    'Destination',
                    'PutInTurn',
                    'StartSend',
                    'LastModified',
                    'Status',
                    'ID',
                    'GUID',
                    'CountPart',
                    'CodeType',
                    'TextMessage'
                )
            );
        }
        return $arReports;
    }

    /**
     * Возвращает значение настройки модуля из БД
     *
     * @param $option string - название настройки
     * @param $site string - идентификатор сайта
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @return string - значение настройки модуля из БД
     */
    public function GetCurrentOption($option, $site = '')
    {
        if (empty($site)) {
            $obFirstSite = Bitrix\Main\SiteTable::getList(array(
                'select' => array('LID'),
                'limit' => 1
            ));
            $firstSite = $obFirstSite->fetch();
            $site = $firstSite['LID'];
        }
        return COption::GetOptionString('rarus.sms4b', $option, false, $site);
    }

    /**
     * Возвращает номер телефона заказа
     *
     * @param $id_order int - id заказа
     * @param $site string - идентификатор сайта
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @return array/bool - массив с данными почтового шаблона/false
     */
    public function GetPhoneOrder($id_order, $site)
    {
        global $SMS4B;

        $code = $option = $SMS4B->GetCurrentOption('phone_number_code', $site);

        $dbOrderList = CSaleOrder::GetList(
            array($by => $order),
            array('ACCOUNT_NUMBER' => $id_order),
            false,
            false,
            array('ID', 'CANCELED', 'ACCOUNT_NUMBER')
        );
        while ($arSaleProp = $dbOrderList->GetNext()) {
            $id_order = $arSaleProp['ID'];
        }

        if (empty($option)) {
            $code = 'sms_events';
        }

        $db_vals = CSaleOrderPropsValue::GetList(
            array('SORT' => 'ASC'),
            array(
                'ORDER_ID' => $id_order,
                'CODE' => $code
            )
        );
        if ($arrOrder = $db_vals->Fetch()) {
            return $arrOrder['VALUE'];
        } else {
            return false;
        }
    }

    /**
     * Возвращает данные почтового шаблона
     *
     * @param $id_template string - тип почтового события
     * @param $site string - идентификатор сайта
     * @param $from mixed - поле "От кого"
     *
     * @return array/bool - массив с данными почтового шаблона/false
     */
    public function GetEventTemplate($id_template, $site, $from = false)
    {
        $arFilter = array(
            'TYPE_ID' => $id_template,
            'FROM' => $from,
            'SITE_ID' => $site
        );

        $rsMess = CEventMessage::GetList($by = 'site_id', $order = 'desc', $arFilter);
        if ($text = $rsMess->Fetch()) {
            return $this->replaceDefaultMakros($text);
        } else {
            return false;
        }
    }

    /**
     * Возвращает текст почтового шаблона по ID
     *
     * @param $id int - ID почтового шаблона
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     *
     * @return array/bool - текст почтового шаблона
     */
    public function GetEventTemplateFromId($id = 0)
    {
        $text = \Bitrix\Main\Mail\Internal\EventMessageTable::getList(array(
            'select' => array('*'),
            'filter' => array('=ID' => $id)
        ));

        $res = $text->fetch();

        return $res['MESSAGE'];
    }

    /**
     * Заменяет стандартные макросы в тексте
     * Заменяет макросы по-умолчанию в тексте
     * @param $text string - исходный текст для замены
     * @return string - Текст с замененными стандартными макросами
     */
    public function replaceDefaultMakros($text)
    {
        $res = CSite::GetByID(SITE_ID);
        $arRes = $res->Fetch();

        $text = str_replace('#DEFAULT_EMAIL_FROM#', COption::GetOptionString('main', 'email_from'), $text);
        $text = str_replace('#SITE_NAME#', $arRes['SITE_NAME'], $text);
        $text = str_replace('#SERVER_NAME#', SITE_SERVER_NAME, $text);
        return $text;
    }

    /**
     * Возвращает массив номеров администраторов
     *
     * @param $site string - ID сайта
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @return array - массив номеров администраторов
     */
    public function GetAdminPhones($site)
    {
        $result = array();
        global $SMS4B;
        $phones = $SMS4B->GetCurrentOption('admin_phone', $site);
        $phones = str_replace(',', ';', $phones);
        $arr = explode(';', $phones);
        foreach ($arr as $phone) {
            if ($SMS4B->is_phone($phone)) {
                $result[] = $phone;
            }
        }
        return $result;
    }

    /**
     * Обработчик события OnBeforeEventAdd
     *
     * @param $event_name string - нозвание типа почтового события
     * @param $site string - идентификатор сайта
     * @param $params array - входные параметры почтового события
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @throws \Bitrix\Main\LoaderException - Исключение подключения модуля
     */
    public function Events($event_name, $site, &$params)
    {
        global $SMS4B;

        //в SendImmediate появился обработчик OnBeforeEventAdd
        //если нет подключения к сервису - Fatal
        if (!is_object($SMS4B)) {
            return true;
        }

        $sender = $SMS4B->GetCurrentOption('defsender', $site);
        $SMS4B->use_translit = COption::GetOptionString('rarus.sms4b', 'use_translit', false, $site);

        $id_sale_status_changed = '';
        if (preg_match("/^SALE_STATUS_CHANGED_(.+?){1}$/", $event_name, $find)) {
            $event_name = 'SALE_STATUS_CHANGED';
            $id_sale_status_changed = $find[1];
            //отмена отправки SMS при смене статуса отгрузки
            //проблема - в модуле sale (16.0.31) для статусов отгрузок не создаются типы и шаблоны почтовых событий
            //и, соответственно, не уходит почта. Поэтому при смене статуса отгрузки используется обработчик SaleShipmentHandler
            if (array_key_exists($id_sale_status_changed, $SMS4B->GetSaleStatus('D'))) {
                return true;
            }
        }

        switch ($event_name) {
            case 'SALE_STATUS_CHANGED':
                $b_send = $SMS4B->GetCurrentOption('event_sale_status_' . $id_sale_status_changed, $site);
                $b_send_admin = $SMS4B->GetCurrentOption('admin_event_sale_status_' . $id_sale_status_changed, $site);

                if ($b_send === 'Y') {
                    $text = $SMS4B->GetEventTemplate('SMS4B_' . $event_name . '_' . $id_sale_status_changed, $site);
                    foreach ($params as $k => $value) {
                        $text['MESSAGE'] = str_replace('#' . $k . '#', $value, $text['MESSAGE']);
                    }
                    $phone_num = $SMS4B->is_phone($SMS4B->GetPhoneOrder($params['ORDER_ID'], $site));

                    if (is_array($text) && $phone_num) {
                        $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                            $phone_num, $sender, $params['ORDER_ID'], false,
                            $event_name . '_' . $id_sale_status_changed);
                    }
                }
                if ($b_send_admin === 'Y') {
                    $text = $SMS4B->GetEventTemplate('SMS4B_ADMIN_' . $event_name . '_' . $id_sale_status_changed,
                        $site);
                    foreach ($params as $k => $value) {
                        $text['MESSAGE'] = str_replace('#' . $k . '#', $value, $text['MESSAGE']);
                    }

                    $phone_num = $SMS4B->is_phone($SMS4B->GetPhoneOrder($params['ORDER_ID'], $site));
                    $text['MESSAGE'] = str_replace('#PHONE_TO#', $phone_num, $text['MESSAGE']);

                    if (is_array($text)) {
                        $phones = (array)$SMS4B->GetAdminPhones($site);
                        foreach ($phones as $phone_num) {
                            $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                                $phone_num, $sender, $params['ORDER_ID'], false,
                                $event_name . '_' . $id_sale_status_changed);
                        }
                    }
                }
                break;

            case 'SUBSCRIBE_CONFIRM':
                $b_send = COption::GetOptionString('rarus.sms4b', 'event_subscribe_confirm');
                $b_send_admin = COption::GetOptionString('rarus.sms4b', 'event_subscribe_confirm');

                if ($b_send === 'Y') {
                    $text = $SMS4B->GetEventTemplate('SMS4B_' . $event_name, $site);

                    foreach ($params as $k => $value) {
                        $text['MESSAGE'] = str_replace('#' . $k . '#', $value, $text['MESSAGE']);
                    }

                    if (preg_match("/^([\+\-\(\)0-9]+?)@phone.sms$/i", $params['EMAIL'], $find)) {
                        $phone_num = $SMS4B->is_phone($find[1]);
                        if ($phone_num && is_array($text)) {
                            $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                                $phone_num, $sender, false, false, $event_name);
                            global $APPLICATION;
                            $params['EMAIL'] = '';
                            $APPLICATION->ThrowException(Loc::getMessage('SMS4B_MAIN_CODE_SEND'));
                            return false;
                        }
                    }
                }
                if ($b_send_admin === 'Y') {
                    $text = $SMS4B->GetEventTemplate('SMS4B_ADMIN_' . $event_name, $site);

                    foreach ($params as $k => $value) {
                        $text['MESSAGE'] = str_replace('#' . $k . '#', $value, $text['MESSAGE']);
                    }

                    if (is_array($text)) {
                        $phones = (array)$SMS4B->GetAdminPhones($site);
                        foreach ($phones as $phone_num) {
                            $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                                $phone_num, $sender, false, false, $event_name);
                        }
                        /* @todo */
                        global $APPLICATION;
                        $params['EMAIL'] = '';
                        $APPLICATION->ThrowException(Loc::getMessage('SMS4B_MAIN_CODE_SEND'));
                        return false;
                    }
                }


                break;
            case 'SALE_ORDER_PAID':
                $b_send = $SMS4B->GetCurrentOption('event_sale_order_paid', $site);
                $b_send_admin = $SMS4B->GetCurrentOption('admin_event_sale_order_paid', $site);

                if ($b_send === 'Y') {
                    $text = $SMS4B->GetEventTemplate('SMS4B_' . $event_name, $site);

                    foreach ($params as $k => $value) {
                        $text['MESSAGE'] = str_replace('#' . $k . '#', $value, $text['MESSAGE']);
                    }

                    $phone_num = $SMS4B->is_phone($SMS4B->GetPhoneOrder($params['ORDER_ID'], $site));

                    if ($phone_num && is_array($text)) {
                        $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                            $phone_num, $sender, $params['ORDER_ID'], false, $event_name);
                    }
                }
                if ($b_send_admin === 'Y') {
                    $text = $SMS4B->GetEventTemplate('SMS4B_ADMIN_' . $event_name, $site);
                    foreach ($params as $k => $value) {
                        $text['MESSAGE'] = str_replace('#' . $k . '#', $value, $text['MESSAGE']);
                    }
                    $phone_num = $SMS4B->is_phone($SMS4B->GetPhoneOrder($params['ORDER_ID'], $site));
                    $text['MESSAGE'] = str_replace('#PHONE_TO#', $phone_num, $text['MESSAGE']);

                    if (is_array($text)) {
                        $phones = $SMS4B->GetAdminPhones($site);
                        foreach ($phones as $phone_num) {
                            $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                                $phone_num, $sender, $params['ORDER_ID'], false, $event_name);
                        }
                    }
                }
                break;
            case 'SALE_ORDER_DELIVERY':
                $b_send = $SMS4B->GetCurrentOption('event_sale_order_delivery', $site);
                $b_send_admin = $SMS4B->GetCurrentOption('admin_event_sale_order_delivery', $site);

                if ($b_send === 'Y') {

                    $text = $SMS4B->GetEventTemplate('SMS4B_' . $event_name, $site);
                    foreach ($params as $k => $value) {
                        $text['MESSAGE'] = str_replace('#' . $k . '#', $value, $text['MESSAGE']);
                    }

                    $phone_num = $SMS4B->is_phone($SMS4B->GetPhoneOrder($params['ORDER_ID'], $site));
                    if ($phone_num && is_array($text)) {
                        $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                            $phone_num, $sender, $params['ORDER_ID'], false, $event_name);
                    }
                }
                if ($b_send_admin === 'Y') {
                    $text = $SMS4B->GetEventTemplate('SMS4B_ADMIN_' . $event_name, $site);
                    foreach ($params as $k => $value) {
                        $text['MESSAGE'] = str_replace('#' . $k . '#', $value, $text['MESSAGE']);
                    }
                    $phone_num = $SMS4B->is_phone($SMS4B->GetPhoneOrder($params['ORDER_ID'], $site));
                    $text['MESSAGE'] = str_replace('#PHONE_TO#', $phone_num, $text['MESSAGE']);

                    if (is_array($text)) {
                        $phones = $SMS4B->GetAdminPhones($site);
                        foreach ($phones as $phone_num) {
                            $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                                $phone_num, $sender, $params['ORDER_ID'], false, $event_name);
                        }
                    }
                }
                break;
            case 'SALE_ORDER_CANCEL':
                $b_send = $SMS4B->GetCurrentOption('event_sale_order_cancel', $site);
                $b_send_admin = $SMS4B->GetCurrentOption('admin_event_sale_order_cancel', $site);

                if ($b_send === 'Y') {
                    $text = $SMS4B->GetEventTemplate('SMS4B_' . $event_name, $site);
                    foreach ($params as $k => $value) {
                        $text['MESSAGE'] = str_replace('#' . $k . '#', $value, $text['MESSAGE']);
                    }

                    $phone_num = $SMS4B->is_phone($SMS4B->GetPhoneOrder($params['ORDER_ID'], $site));
                    if ($phone_num && is_array($text)) {
                        $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                            $phone_num, $sender, $params['ORDER_ID'], false, $event_name);
                    }
                }
                if ($b_send_admin === 'Y') {
                    $text = $SMS4B->GetEventTemplate('SMS4B_ADMIN_' . $event_name, $site);
                    foreach ($params as $k => $value) {
                        $text['MESSAGE'] = str_replace('#' . $k . '#', $value, $text['MESSAGE']);
                    }
                    $phone_num = $SMS4B->is_phone($SMS4B->GetPhoneOrder($params['ORDER_ID'], $site));
                    $text['MESSAGE'] = str_replace('#PHONE_TO#', $phone_num, $text['MESSAGE']);

                    if (is_array($text)) {
                        $phones = $SMS4B->GetAdminPhones($site);
                        foreach ($phones as $phone_num) {
                            $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                                $phone_num, $sender, $params['ORDER_ID'], false, $event_name);
                        }
                    }
                }
                break;
            case 'SALE_NEW_ORDER':
                $b_send = $SMS4B->GetCurrentOption('event_sale_new_order', $site);
                $b_send_admin = $SMS4B->GetCurrentOption('admin_event_sale_new_order', $site);

                if ($b_send === 'Y') {
                    $text = $SMS4B->GetEventTemplate('SMS4B_' . $event_name, $site);

                    foreach ($params as $k => $value) {
                        $text['MESSAGE'] = str_replace('#' . $k . '#', $value, $text['MESSAGE']);
                    }
                    $phone_num = $SMS4B->is_phone($SMS4B->GetPhoneOrder($params['ORDER_ID'], $site));
                    if ($phone_num && is_array($text)) {
                        $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                            $phone_num, $sender, $params['ORDER_ID'], false, $event_name);
                    }
                }
                if ($b_send_admin === 'Y') {
                    $text = $SMS4B->GetEventTemplate('SMS4B_ADMIN_' . $event_name, $site);
                    foreach ($params as $k => $value) {
                        $text['MESSAGE'] = str_replace('#' . $k . '#', $value, $text['MESSAGE']);
                    }

                    $phone_num = $SMS4B->is_phone($SMS4B->GetPhoneOrder($params['ORDER_ID'], $site));
                    $text['MESSAGE'] = str_replace('#PHONE_TO#', $phone_num, $text['MESSAGE']);

                    if (is_array($text)) {
                        $phones = $SMS4B->GetAdminPhones($site);
                        foreach ($phones as $phone_num) {

                            $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                                $phone_num, $sender, $params['ORDER_ID'], false, $event_name);
                        }
                    }
                }
                break;

            case 'TICKET_NEW_FOR_TECHSUPPORT':
            case 'TICKET_CHANGE_FOR_TECHSUPPORT':
                $b_send = $SMS4B->GetCurrentOption('event_ticket_new_for_techsupport', $site);
                $b_send_admin = $SMS4B->GetCurrentOption('admin_event_ticket_new_for_techsupport', $site);

                if ($b_send === 'Y') {
                    $text = $SMS4B->GetEventTemplate('SMS4B_' . $event_name, $site);
                    //take groups id of support-group and admins
                    $sgroup = array_merge(CTicket::GetGroupsByRole('T'), CTicket::GetGroupsByRole('W'));
                    $filter = Array('ACTIVE' => 'Y');
                    if ($params['RESPONSIBLE_USER_ID'] == '') {
                        $filter['GROUPS_ID'] = $sgroup;
                        $filter['EMAIL'] = $params['SUPPORT_EMAIL'];
                    } else {
                        $filter['ID'] = $params['RESPONSIBLE_USER_ID'];
                    }

                    foreach ($params as $k => $value) {
                        $text['MESSAGE'] = str_replace('#' . $k . '#', $value, $text['MESSAGE']);
                    }

                    $rsUsers = CUser::GetList($by = 'id', $order = 'desc', $filter);
                    while ($ob = $rsUsers->Fetch()) {
                        $phone_num = $SMS4B->is_phone($ob['WORK_PHONE']);
                        if ($phone_num && is_array($text)) {
                            $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                                $phone_num, $sender, false, false, $event_name);
                        }
                    }
                }
                if ($b_send_admin === 'Y') {
                    $text = $SMS4B->GetEventTemplate('SMS4B_ADMIN_' . $event_name, $site);
                    foreach ($params as $k => $value) {
                        $text['MESSAGE'] = str_replace('#' . $k . '#', $value, $text['MESSAGE']);
                    }

                    if (is_array($text)) {
                        $phones = $SMS4B->GetAdminPhones($site);
                        foreach ($phones as $phone_num) {
                            $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                                $phone_num, $sender, false, false, $event_name);
                        }
                    }
                }
                break;
            default:
                //customised post event
                $smsText = $SMS4B->GetEventTemplate('SMS4B_' . $event_name, $site, 'SMS4B_USER');
                $userPhone = $smsText['EMAIL_TO'];
                $smsAdminText = $SMS4B->GetEventTemplate('SMS4B_' . $event_name, $site, 'SMS4B_ADMIN');
                if (!empty($smsText)) {
                    if ($SMS4B->is_phone($userPhone)) {
                        $userPhoneSend = $SMS4B->is_phone($userPhone);
                    } else {
                        $macro = $params[trim($userPhone, '#')];

                        if ($userPhone === '#ORDER_ID#') {
                            $userPhoneSend = $SMS4B->is_phone($SMS4B->GetPhoneOrder($params['ORDER_ID']));
                        } else {
                            if ($SMS4B->is_phone($macro)) {
                                $userPhoneSend = $SMS4B->is_phone($macro);
                            } else {
                                $userPhoneSend = $SMS4B->SearchUserPhone($macro);
                            }
                        }
                    }

                    if ($userPhoneSend) {
                        foreach ($params as $index => $value) {
                            $smsText['MESSAGE'] = str_replace('#' . $index . '#', $value, $smsText['MESSAGE']);
                        }
                        $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($smsText['MESSAGE']) : $smsText['MESSAGE'],
                            $userPhoneSend, $sender, false, false, $event_name);
                    }
                }
                if (!empty($smsAdminText)) {
                    foreach ($params as $index => $value) {
                        $smsAdminText['MESSAGE'] = str_replace('#' . $index . '#', $value, $smsAdminText['MESSAGE']);
                    }
                    $phones = $SMS4B->GetAdminPhones($site);
                    foreach ($phones as $phone_num) {
                        $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($smsAdminText['MESSAGE']) : $smsAdminText['MESSAGE'],
                            $phone_num, $sender, false, false, $event_name);
                    }
                }
                break;
        }
    }

    /**
     * Поиск телефона пользователя
     *
     * @param $value mixed - email или ID пользователя
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @return string - номер телефона
     */
    public function SearchUserPhone($value)
    {
        global $SMS4B;
        $propertyPhone = $SMS4B->GetCurrentOption('user_property_phone', SITE_ID);

        $filter = Array('ACTIVE' => 'Y');
        $userPhone = false;

        //это ID?
        if (is_numeric($value)) {
            $filter['ID'] = $value;
        }//это Email?
        elseif (filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $filter['EMAIL'] = $value;
        } else {
            return $userPhone;
        }

        if (strlen(trim($propertyPhone)) > 0) {
            $rsUsers = CUser::GetList($by = 'ID', $order = 'asc', $filter, array('SELECT' => array('UF_*')));
            if ($ob = $rsUsers->Fetch()) {
                $userPhone = $ob[$propertyPhone];
            }
        } else {
            $rsUsers = CUser::GetList($by = 'ID', $order = 'asc', $filter);
            if ($ob = $rsUsers->Fetch()) {
                if ($SMS4B->is_phone($ob['PERSONAL_PHONE'])) {
                    $userPhone = $SMS4B->is_phone($ob['PERSONAL_PHONE']);
                } elseif ($SMS4B->is_phone($ob['PERSONAL_MOBILE'])) {
                    $userPhone = $SMS4B->is_phone($ob['PERSONAL_MOBILE']);
                } elseif ($SMS4B->is_phone($ob['WORK_PHONE'])) {
                    $userPhone = $SMS4B->is_phone($ob['WORK_PHONE']);
                }
            }
        }
        return $userPhone;
    }

    /**
     * Обработчик события OnTaskAdd
     *
     * @param $ID int - ID задачи
     * @param $arFields array - массив с данными задачи
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     */
    public function TaskAdded($ID, $arFields)
    {
        global $SMS4B;

        //Для поддержки старого макроса #TASK#
        $arFields['TASK'] = $arFields['TITLE'];

        $sender = $SMS4B->GetCurrentOption('defsender', SITE_ID);
        if ($SMS4B->CheckTaskPriority($arFields['PRIORITY'], 'add', SITE_ID)
            === 'Y' && $SMS4B->checkGroupPerm($arFields['GROUP_ID'])
        ) {
            $phone_num = $SMS4B->is_phone($SMS4B->SearchUserPhone($arFields['RESPONSIBLE_ID']));
            $SMS4B->SendSmsByTemplate($sender, $phone_num, 'SMS4B_TASK_ADD', $arFields, 'TASK_ADD');
        }
        if ($SMS4B->CheckTaskPriority($arFields['PRIORITY'], 'admin_add', SITE_ID)
            === 'Y' && $SMS4B->checkGroupPerm($arFields['GROUP_ID'])
        ) {
            $adminPhones = (array)$SMS4B->GetAdminPhones(SITE_ID);
            foreach ($adminPhones as $phoneNum) {
                $SMS4B->SendSmsByTemplate($sender, $phoneNum, 'SMS4B_ADMIN_TASK_ADD', $arFields, 'ADMIN_TASK_ADD');
            }
        }
    }

    /**
     * Обработчик события OnTaskUpdate
     *
     * @param $ID int - ID задачи
     * @param $arFields array - массив с данными задачи
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     * @return bool - результат выполнения
     */
    public function TaskUpdated($ID, $arFields)
    {
        global $SMS4B;

        //Для поддержки старого макроса #TASK#
        $arFields['TASK'] = $arFields['TITLE'];

        $sender = $SMS4B->GetCurrentOption('defsender', SITE_ID);
        if ($SMS4B->CheckTaskPriority($arFields['PRIORITY'], 'update', SITE_ID)
            === 'Y' && $SMS4B->checkGroupPerm($arFields['GROUP_ID'])
        ) {
            $phone_num = $SMS4B->is_phone($SMS4B->SearchUserPhone($arFields['RESPONSIBLE_ID']));
            $SMS4B->SendSmsByTemplate($sender, $phone_num, 'SMS4B_TASK_UPDATE', $arFields, 'TASK_UPDATE');
        }
        if ($SMS4B->CheckTaskPriority($arFields['PRIORITY'], 'admin_update', SITE_ID)
            === 'Y' && $SMS4B->checkGroupPerm($arFields['GROUP_ID'])
        ) {
            $adminPhones = (array)$SMS4B->GetAdminPhones(SITE_ID);
            foreach ($adminPhones as $phoneNum) {
                $SMS4B->SendSmsByTemplate($sender, $phoneNum, 'SMS4B_ADMIN_TASK_UPDATE', $arFields,
                    'ADMIN_TASK_UPDATE');
            }
        }
    }

    /**
     * Обработчик события OnBeforeTaskDelete
     *
     * @param $ID int - ID задачи
     * @param $arFields array - массив с данными задачи
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     * @return bool - результат выполнения
     */
    public function BeforeTaskDeleted($ID, $arFields)
    {
        global $SMS4B;

        //Для поддержки старого макроса #TASK#
        $arFields['TASK'] = $arFields['TITLE'];

        $sender = $SMS4B->GetCurrentOption('defsender', SITE_ID);
        if ($SMS4B->CheckTaskPriority($arFields['PRIORITY'], 'delete', SITE_ID)
            === 'Y' && $SMS4B->checkGroupPerm($arFields['GROUP_ID'])
        ) {
            $phone_num = $SMS4B->is_phone($SMS4B->SearchUserPhone($arFields['RESPONSIBLE_ID']));
            $SMS4B->SendSmsByTemplate($sender, $phone_num, 'SMS4B_TASK_DELETE', $arFields, 'TASK_DELETE');
        }
        if ($SMS4B->CheckTaskPriority($arFields['PRIORITY'], 'admin_delete', SITE_ID)
            === 'Y' && $SMS4B->checkGroupPerm($arFields['GROUP_ID'])
        ) {
            $adminPhones = (array)$SMS4B->GetAdminPhones(SITE_ID);
            foreach ($adminPhones as $phoneNum) {
                $SMS4B->SendSmsByTemplate($sender, $phoneNum, 'SMS4B_ADMIN_TASK_DELETE', $arFields,
                    'ADMIN_TASK_DELETE');
            }
        }
    }

    /**
     * Обработчик события OnAfterCommentAdd
     *
     * @param $eventName string - Название события
     * @param $resolveFlag string - Название флага разрешения отправки
     * @param $id int - ID комментария
     * @param $comment array - Массив с данными комментария
     *
     * @throws \Bitrix\Main\LoaderException - Исключение подключения модуля
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     * @return false - результат выполнения
     */
    public function AddNewCommentTask($eventName, $resolveFlag, $id, $comment)
    {
        global $SMS4B;

        if ($SMS4B->GetCurrentOption($resolveFlag, SITE_ID) === 'Y') {
            $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_START_EVENT_ADD_COMMENT_TASKS'));

            if (!\Bitrix\Main\Loader::includeModule('tasks')) {
                $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_LOG_DO_NOT_INSTALL_TASKS'));
                $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_END_EVENT_ADD_COMMENT_TASKS') . PHP_EOL);
                return false;
            }

            $sender = $SMS4B->GetCurrentOption('defsender', SITE_ID);

            $result = \Bitrix\Tasks\TaskTable::getList(array(
                'select' => array('*'),
                'filter' => array(
                    '=ID' => $comment['TASK_ID']
                )
            ));

            $res = $result->Fetch();

            if (!empty($id) && !empty($res) && !empty($comment['TASK_ID']) && $SMS4B->checkGroupPerm($res['GROUP_ID'])) {
                $res['COMMENT_TEXT'] = $comment['COMMENT_TEXT'];

                $text = $SMS4B->GetEventTemplate('SMS4B_' . strtoupper($resolveFlag), SITE_ID);


                $responsible = $SMS4B->is_phone($SMS4B->SearchUserPhone($res['RESPONSIBLE_ID']));

                $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_RESPONS_ID') . $res['RESPONSIBLE_ID']);
                $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_LOG_GET_PHONE') . $responsible);

                foreach ($res as $index => $value) {
                    $text['MESSAGE'] = str_replace('#' . $index . '#', $value, $text['MESSAGE']);
                }

                if (!empty($responsible) && is_array($text)) {
                    $SMS4B->SendSMS(
                        $SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                        $responsible,
                        $sender,
                        false,
                        false,
                        $eventName
                    );
                }
            } else {
                $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_NO_SEARCH_TASKS'));
            }
            $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_END_EVENT_ADD_COMMENT_TASKS') . PHP_EOL);
        }
    }

    /**
     * @deprecated use SearchUserPhone($value)
     * Возвращает телефон пользователя по его ID
     *
     * @param $userID integer - ID пользователя
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @return mixed - номер телефона или false
     */
    public function GetUserPhone($userID)
    {
        return $this->SearchUserPhone($userID);
    }

    /**
     * Проверка возможности отправки сообщения для обработчиков задач
     *
     * @param $priority int - индекс приоритета задачи
     * @param $task string - действие над задачей
     * @param $site string - ID сайта
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @return string - запись из БД ('Y' или '')
     */
    public function CheckTaskPriority($priority, $task, $site)
    {
        global $SMS4B;
        $result = '';
        if ((int)$priority === 0 || empty($priority)) {
            $result = $SMS4B->GetCurrentOption($task . '_low_task', $site);
        } elseif ((int)$priority === 1) {
            $result = $SMS4B->GetCurrentOption($task . '_middle_task', $site);
        } elseif ((int)$priority === 2) {
            $result = $SMS4B->GetCurrentOption($task . '_hight_task', $site);
        }
        return $result;
    }

    /**
     * Обработчик события BeforePostingSendMail
     *
     * @param $arFields array - массив с данными рассылки
     *
     * @return bool/array - результат выполнения
     */
    public function EventsPosting($arFields)
    {
        $obPosting = new CPosting();

        $rsPosting = $obPosting->GetByID($arFields['POSTING_ID']);
        $arPosting = $rsPosting->Fetch();
        $rass_from = $arPosting['FROM_FIELD'];
        $rass_to = $arFields['EMAIL'];

        global $SMS4B;
        if (preg_match("/^([\+\-\(\)0-9a-zA-Z]+?)@phone.sms$/", $rass_from,
                $find) || strtoupper($rass_from) === 'PHONE@PHONE.SMS'
        ) {
            $rass_from = strtoupper($rass_from) === 'PHONE@PHONE.SMS' ? $SMS4B->DefSender : $find[1];

            preg_match("/^([\+\-\(\)0-9]+?)@phone.sms$/", $rass_to, $find);

            $phone_num = $SMS4B->is_phone($find[1]);

            if ($rass_from && $phone_num) {
                $rsPosting = $obPosting->GetByID($arFields['POSTING_ID']);
                $arPosting = $rsPosting->Fetch();
                $arPosting['SUBJECT'] .= '';
                $mess = '';
                $mess .= $SMS4B->use_translit === 'Y' ? $SMS4B->Translit($arFields['BODY']) : $arFields['BODY'];

                //only if message of type "text"
                if ($arPosting['BODY_TYPE'] === 'text') {
                    $SMS4B->SendSMS($mess, $phone_num, $rass_from, false, $arFields['POSTING_ID']);
                }
            }
        } elseif (preg_match("/^([\+\-\(\)0-9]+?)@phone.sms$/", $rass_to, $find)) {
            return false;
        }

        return $arFields;
    }

    /**
     * @deprecated 1.4.0 use SendSmsSaveGroup()
     * Отправка одиночного сообщения
     *
     * @param $message string - текст сообщения
     * @param $to string - строка с номером
     * @param $sender string - отправитель
     * @param $IDOrder int - ID заказа
     * @param $Posting int - ID рассылки
     * @param $TypeEvents string - тип события
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @return bool - результат выполнения
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     */
    public function SendSMS($message, $to, $sender = '', $IDOrder = 0, $Posting = 0, $TypeEvents = '')
    {
        try {
            $res = $this->SendSmsSaveGroup(array($to => $message), $sender, null, null, null, null, $IDOrder,
                $TypeEvents, $Posting);
            if ($res[0]['Result'] <= 0) {
                return false;
            } else {
                return true;
            }
        } catch (Sms4bException $e) {
            return false;
        }
    }

    /**
     * @deprecated 1.4.0 use SendSmsSaveGroup()
     * Отправка сообщения на несколько номеров
     *
     * @param $message string - текст сообщения
     * @param $to mixed - строка или массив с номерами
     * @param $sender string - отправитель
     * @param $startUp_p string - дата старта рассылки
     * @param $dateActual_p string - дата актуальности рассылки
     * @param $period_p string - запрет отправки в определенный период веремени в буквенном представлении
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     * @return array - результат выполнения
     */
    public function SendSmsPack($message, $to, $sender = '', $startUp_p = '', $dateActual_p = '', $period_p = '')
    {
        $arRes = $arPhonesMessages = array();
        try {
            if (is_array($to)) {
                foreach ((array)$to as $phone) {
                    $arPhonesMessages[$phone] = $message;
                }
            } else {
                $arPhonesMessages[$to] = $message;
            }

            $res = $this->SendSmsSaveGroup($arPhonesMessages, $sender, $startUp_p, $dateActual_p, $period_p);

        } catch (Sms4bException $e) {
            return array('WAS_SEND' => 0, 'NOT_SEND' => count($arPhonesMessages));
        }

        foreach ($res as $val) {
            if ($val['Result'] <= 0) {
                $arRes['WAS_SEND']++;
            } else {
                $arRes['NOT_SEND']++;
            }
        }

        return $arRes;
    }

    /**
     * Отправка сообщения на несколько номеров
     *
     * @param $arPhonesMessages array - массив вида array("PHONE"=>"MESSAGE")
     * @param $sender mixed - отправитель
     * @param $startSend string - дата старта рассылки
     * @param $dateActual string - дата актуальности рассылки
     * @param $period string - запрет отправки в определенный период веремени в буквенном представлении
     * @param $regular mixed - флаг регулярной отправки
     * @param $orderId int - ID заказа
     * @param $typeEvent string - название события
     * @param $posting string - ID почтовой рассылки
     *
     * @throws \Rarus\Sms4b\Sms4bException - ошибки валидации входных параметров, ошибки сервиса
     * @throws \Bitrix\Main\ObjectException - ошибки объекта dateTime
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     *
     * @return array - результат выполнения
     */
    public function SendSmsSaveGroup(
        $arPhonesMessages,
        $sender = '',
        $startSend = '',
        $dateActual = '',
        $period = '',
        $regular = '',
        $orderId = 0,
        $typeEvent = '',
        $posting = ''
    ) {
        global $SMS4B;
        if (COption::GetOptionString('rarus.sms4b', 'module_enabled', false) !== 'Y') {
            throw new Sms4bException(Loc::getMessage('SMS4B_MAIN_NO_ENABLED'));
        }

        $service = \Rarus\Sms4b\Sms4bClient::getInstance();

        $sender = empty($sender) ? $SMS4B->DefSender : $sender;
        $period = empty($period) ? $SMS4B->GetCurrentOption('restricted_time', SITE_ID) : $period;

        foreach ($arPhonesMessages as $phone => $message) {
            if (empty($message) || !$SMS4B->is_phone($phone)) {
                unset($arPhonesMessages[$phone]);
            }
        }

        if (empty($arPhonesMessages)) {
            throw new Sms4bException(Loc::getMessage('SMS4B_MAIN_EMPTY_SEND_PROP'));
        }

        $now = new \Bitrix\Main\Type\DateTime();
        $obStartSend = !empty($startSend) ? \Bitrix\Main\Type\DateTime::createFromTimestamp(strtotime($startSend))
            : new \Bitrix\Main\Type\DateTime();
        $obDateActual = !empty($dateActual) ? \Bitrix\Main\Type\DateTime::createFromTimestamp(strtotime($dateActual))
            : $now->add(self::DEFAULT_ACTUAL_DAYS . 'D');
        $regular = !empty($regular) ? -2 : -1;
        $arParam = $smsPackage = array();

        //Выполнение обработчиков события OnBeforeSmsSend
        $arEvents = EventManager::getInstance()->findEventHandlers('rarus.sms4b', 'OnBeforeSmsSend');
        foreach ($arEvents as $arEvent) {
            ExecuteModuleEventEx($arEvent, Array(&$arPhonesMessages, $typeEvent, $sender));
        }

        foreach ($arPhonesMessages as $phone => $text) {
            if (LANG_CHARSET !== 'UTF-8') {
                //Принудительная конвертация всей строки в UTF-8 (для возможности распознавания спец-символов) и
                //преобразование HTML-кодов в символы
                $text = html_entity_decode(mb_convert_encoding($text, 'UTF-8', LANG_CHARSET), ENT_COMPAT | ENT_HTML401,
                    'UTF-8');
            }

            $body = $SMS4B->enCodeMessage($text);
            $encoded = $SMS4B->get_type_of_encoding($text);
            $oneSms = array(
                'G' => $SMS4B->CreateGuid(),
                'D' => $phone,
                'B' => $body,
                'E' => $encoded
            );
            $smsPackage[] = $oneSms;

            $arParam[] = array(
                'GUID' => $oneSms['G'],
                'SenderName' => $sender,
                'Destination' => $phone,
                'StartSend' => $obStartSend,
                'LastModified' => $obStartSend,
                'CountPart' => '-1',
                'SendPart' => '-1',
                'CodeType' => $encoded,
                'TextMessage' => $SMS4B->decode($oneSms['B'], $oneSms['E']),
                'Sale_Order' => $orderId ?: 0,
                'Status' => self::INPROCESS,
                'Posting' => $posting ?: 0,
                'Events' => $typeEvent ?: ''
            );
        }

        $smsPackage = array($smsPackage);
        if (count($smsPackage) > $SMS4B->maxPackage) {
            $smsPackage = array_chunk($smsPackage, $SMS4B->maxPackage, true);
        }

        if (!$this->UpdateSID()) {
            throw new Sms4bException(Loc::getMessage('SMS4B_MAIN_SESSION_NOT_INSTALL'));
        }

        foreach ($smsPackage as $key => $smsList) {
            $sendParam = array(
                'SessionId' => $this->GetSID(),
                'Group' => !empty($res['Group']) ? $res['Group'] : $regular,
                'Source' => $sender,
                'Encoding' => '',
                'Body' => '',
                'Off' => $obDateActual->format(self::SERVICE_DATE_TIME_FORMAT),
                'Start' => $obStartSend->format(self::SERVICE_DATE_TIME_FORMAT),
                'Period' => $period,
                'List' => array_values($smsList)
            );

            $res = $service->GroupSMS($sendParam);

            if ($res['Result'] <= 0) {
                foreach ((array)$smsList as $keySms => $arSms) {
                    $arParam[$keySms]['Result'] = $res['Result'];
                }
            } else {
                if (count($smsList) === 1) {
                    $arParam[0]['Result'] = $res['Result'];
                } else {
                    foreach ($res['List']['CheckSMSList'] as $listSms) {
                        foreach ((array)$smsList as $keySms => $arSms) {
                            if ($listSms['G'] === $arSms['G']) {
                                $arParam[$keySms]['Result'] = $listSms['R'];
                            }
                        }
                    }
                }
            }
        }

        //Выполнение обработчиков события OnAfterSmsSend
        $arEvents = EventManager::getInstance()->findEventHandlers('rarus.sms4b', 'OnAfterSmsSend');
        foreach ($arEvents as $arEvent) {
            ExecuteModuleEventEx($arEvent, Array($arParam));
        }

        $SMS4B->ArrayAdd($arParam);
        return $arParam;
    }

    /**
     * Обновление статусов смс
     *
     * @param $ID int - ID смс в БД
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     * @return bool - результат выполнения
     */
    public function UpdateStatusSms($ID)
    {
        $sms = $this->GetByID($ID);
        if ($sms['id'] > 0 && ($sms['CountPart'] == 0 || $sms['CountPart'] <> $sms['SendPart'])) {
            $arrParam = array();
            if ($sms['CountPart'] == 0) {
                $ston = $this->get_ton($sms['SenderName']);
                $snpi = $this->get_npi($sms['SenderName']);

                $dton = $this->get_ton($sms['Destination']);
                $dnpi = $this->get_npi($sms['Destination']);

                $body = $this->enCodeMessage($sms['TextMessage']);
                $encoded = $this->get_type_of_encoding($sms['TextMessage']);

                $date_actual = date('Ymd H:i:s', time() + 86400 * 2);


                $sms['Destination'] = $this->is_phone($sms['Destination']);
                $arrParam = array(
                    'SessionID' => $this->sid,/*session*/
                    'guid' => $sms['GUID'],/*guid*/
                    'Destination' => $sms['Destination'],/*address of destination*/
                    'Source' => $sms['SenderName'],/*address of sender*/
                    'Body' => $body,/*message text*/
                    'Encoded' => $encoded,/*encoding type of message*/
                    'dton' => $dton,/*number type of destination address*/
                    'dnpi' => $dnpi,/*numeric plan indicator*/
                    'ston' => $ston,/*number type of sender address*/
                    'snpi' => $snpi,/*numeric plan indicator*/
                    'TimeOff' => $date_actual,/*urgency time of message*/
                    'Priority' => 0, /*priority*/
                    'NoRequest' => 0/*delivery report*/
                );
                $sms['StartSend'] = date('Y-m-d H:i:s', time());
            } elseif ($sms['CountPart'] <> $sms['SendPart']) {
                $arrParam = array(
                    'SessionID' => $this->sid,
                    'guid' => $sms['GUID'],
                    'Destination' => '',
                    'Source' => '',
                    'Body' => '',
                    'Encoded' => 0,
                    'dton' => 0,
                    'dnpi' => 0,
                    'ston' => 0,
                    'snpi' => 0,
                    'TimeOff' => 0,
                    'Priority' => 0,
                    'NoRequest' => 0
                );
            }

            $resSendMess = $this->GetSOAP('SaveMessage', $arrParam);

            $sms['StartSend'] = ($sms['StartSend'] === '0000-00-00 00:00:00') ? date('Y-m-d H:i:s',
                time()) : $sms['StartSend'];
            $sms['LastModified'] = ($sms['LastModified'] === '0000-00-00 00:00:00') ? date('Y-m-d H:i:s',
                time()) : $sms['LastModified'];

            $arrparam[] = array(
                'GUID' => $sms['GUID'],
                'SenderName' => $sms['SenderName'],
                'Destination' => $sms['Destination'],
                'StartSend' => \Bitrix\Main\Type\DateTime::createFromTimestamp(strtotime($sms['StartSend'])),
                'LastModified' => new \Bitrix\Main\Type\DateTime(),
                'CountPart' => $resSendMess['SEND'],
                'SendPart' => $resSendMess['OK'],
                'CodeType' => $sms['CodeType'],
                'TextMessage' => $sms['TextMessage']
            );

            $this->ArrayAdd($arrparam);

            if ($resSendMess) {
                return true;
            } else {
                return false;
            }
        } else {
            return false;
        }
    }

    /**
     * Обовление SID
     *
     * @return bool - результат выполнения
     */
    protected function UpdateSID()
    {
        $this->sid = COption::GetOptionString('rarus.sms4b', 'sid');
        if (!$this->GetSOAP('AccountParams', array('SessionID' => $this->sid))) {
            $this->sid = 0;
            $arParam = array(
                'Login' => $this->login,
                'Password' => $this->password,
                'Gmt' => $this->gmt
            );

            if ($this->GetSOAP('StartSession', $arParam)) {
                $this->GetSOAP('AccountParams', array('SessionID' => $this->sid));
            } else {
                $this->sid = 0;
                $this->serv_addr = 'https://s.sms4b.ru';
                $arParam = array(
                    'Login' => $this->login,
                    'Password' => $this->password,
                    'Gmt' => $this->gmt
                );
                if ($this->GetSOAP('StartSession', $arParam)) {
                    COption::RemoveOption('rarus.sms4b', 'timer_server_not_available');
                    $this->GetSOAP('AccountParams', array('SessionID' => $this->sid));
                } elseif (COption::GetOptionString('rarus.sms4b', 'send_email') === 'Y' &&
                    COption::GetOptionString('rarus.sms4b', 'error_send_letter') !== 'Y'
                ) {
                    $now = new \Bitrix\Main\Type\DateTime();
                    $lastCheckDate = COption::GetOptionString('rarus.sms4b', 'timer_server_not_available');
                    if (empty($lastCheckDate)) {
                        COption::SetOptionString('rarus.sms4b', 'timer_server_not_available',
                            $now->add('T' . self::TIMER_ERROR_MAIL_SEND . 'M'));
                        return true;
                    }

                    $lastCheckDate = new \Bitrix\Main\Type\DateTime($lastCheckDate);
                    if ($now->getTimestamp() > $lastCheckDate->getTimestamp()) {
                        if (CEvent::SendImmediate('SMS4B_ADMIN_SEND', CSite::GetDefSite(),
                                array('DEFAULT_EMAIL_FROM' => COption::GetOptionString('main', 'email_from')))
                            === Mail\Event::SEND_RESULT_SUCCESS
                        ) {
                            COption::SetOptionString('rarus.sms4b', 'error_send_letter', 'Y');
                        }
                    }
                }
            }
        } else {
            COption::RemoveOption('rarus.sms4b', 'timer_server_not_available');
        }
        return true;
    }

    /**
     * Закрывает сессию
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @return bool результат выполнения
     */
    public function CloseSID()
    {
        if ($this->GetSOAP('CloseSession', array('SessionID' => $this->GetCurrentOption('sid', SITE_ID)))) {
            COption::SetOptionString('rarus.sms4b', 'sid', 0);
            return true;
        } else {
            return false;
        }
    }


    /**
     * Загрузка входящих сообщений
     */
    public function LoadIncoming()
    {
        $loadMore = true;
        while ($loadMore) {
            $incs = (array)$this->GetSOAP('LoadIn',
                array('SessionID' => $this->sid, 'StartChanges' => $this->inc_date));
            if (!$incs) {
                $loadMore = false;
            }

            if (!empty($this->inc_date)) {
                $time = explode(' ', $this->inc_date);
                $arrd = explode('-', $time[0]);
                $arrt = explode(':', $time[1]);
                $time = mktime($arrt[0], $arrt[1], $arrt[2], $arrd[1], $arrd[2], $arrd[0]);
            } else {
                $time = 0;
            }

            foreach ($incs as $inc) {
                if (empty($inc['Body'])) {
                    $inc['Body'] = ' ';
                }
                if (count($inc) > 0) {
                    $this->AddIncoming($inc);
                }

                $inc['Moment'] = explode('.', $inc['Moment']);
                $inc['Moment'] = explode(' ', $inc['Moment'][0]);
                $arrd = explode('-', $inc['Moment'][0]);
                $arrt = explode(':', $inc['Moment'][1]);

                $timen = mktime($arrt[0], $arrt[1], $arrt[2], $arrd[1], $arrd[2], $arrd[0]);
                if ($timen >= $time) {
                    $time = $timen + 1;
                    COption::SetOptionString('rarus.sms4b', 'inc_date', date('Y-m-d H:i:s', $time));
                    $this->inc_date = date('Y-m-d H:i:s', $time);
                }

            }
        }
    }

    /**
     * Конвертация формата даты для использования в форме
     *
     * @param $date string - дата
     *
     * @return string - отформатированная дата или -1
     */
    public function GetFormatDateForSmsForm($date)
    {
        $date = htmlspecialchars($date);

        $forShortTime = date('H:i:s');
        if (preg_match("/^\d{2}\.\d{2}\.\d{4}$/", $date)) {
            return ConvertDateTime($date, "YYYYMMDD $forShortTime", 'ru');
        }
        if (preg_match("/^\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}$/", $date)) {
            return ConvertDateTime($date, 'YYYYMMDD HH:MI:SS', 'ru');
        }

        return -1;
    }

    /**
     * Конвертация формата даты для записи в БД
     *
     * @param $date string - дата
     *
     * @return string - отформатированная дата или -1
     */
    public function ForDb($date)
    {
        $date = htmlspecialchars($date);

        $forShortTime = date('H:i:s');
        if (preg_match("/^\d{2}\.\d{2}\.\d{4}$/", $date)) {
            return ConvertDateTime($date, "YYYY-MM-DD $forShortTime", 'ru');
        }
        if (preg_match("/^\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}$/", $date)) {
            return ConvertDateTime($date, 'YYYY-MM-DD HH:MI:SS', 'ru');
        }

        return -1;
    }

    /**
     * Обработчик событий CRM
     *
     * @param $eventName string - имя события
     * @param $resolveFlag string - имя параметра в таблице b_option
     * @param $eventData mixed - данные события
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     * @return boolean - результат выполнения
     */
    public function CrmEventsHandler($eventName, $resolveFlag, $eventData)
    {
        global $SMS4B;

        if (!CModule::IncludeModule('crm')) {
            $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_LOG_DO_NOT_INSTALL_CRM'));
            $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_END_CRM_EVENT') . $eventName . PHP_EOL);
            return false;
        }

        //Отдельно для статусов
        if ($resolveFlag === 'change_stat_lead_crm') {
            $canSend = $SMS4B->GetCurrentOption($resolveFlag . '_' . $eventData['STATUS_ID'], SITE_ID);
            $canSendAdmin = $SMS4B->GetCurrentOption('admin_' . $resolveFlag . '_' . $eventData['STATUS_ID'], SITE_ID);
        } else {
            if ($resolveFlag === 'change_stat_deal_crm') {
                $canSend = $SMS4B->GetCurrentOption($resolveFlag . '_' . $eventData['STAGE_ID'], SITE_ID);
                $canSendAdmin = $SMS4B->GetCurrentOption('admin_' . $resolveFlag . '_' . $eventData['STAGE_ID'],
                    SITE_ID);
            } else {
                $canSend = $SMS4B->GetCurrentOption($resolveFlag, SITE_ID);
                $canSendAdmin = $SMS4B->GetCurrentOption('admin_' . $resolveFlag, SITE_ID);
            }
        }

        if ($canSend === 'Y' || $canSendAdmin === 'Y') {
            $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_START_CRM_EVENT') . $eventName);

            switch ($eventName) {
                //Для событий лидов
                case 'OnAfterCrmLeadAdd':
                    $eventData = array_merge($eventData, $SMS4B->GetLeadData($eventData['ID']));
                    break;
                case 'OnAfterCrmLeadUpdate':
                    $eventData = array_merge($eventData, $SMS4B->GetLeadData($eventData['ID']));
                    //Изменился статус
                    if (!empty($_SESSION['SMS4B']['LEAD_BUF'][$eventData['ID']])) {
                        if ($eventData['STATUS_ID'] !== $_SESSION['SMS4B']['LEAD_BUF'][$eventData['ID']]) {
                            $eventData['OLD_STAT'] = $_SESSION['SMS4B']['LEAD_BUF'][$eventData['ID']];
                            $resolveFlag = 'change_stat_lead_crm';
                        }
                        unset($_SESSION['SMS4B']['LEAD_BUF'][$eventData['ID']]);
                    }
                    break;
                case 'OnBeforeCrmLeadDelete':
                    $eventData = $SMS4B->GetLeadData($eventData);
                    break;
                case 'OnBeforeCrmLeadUpdate':
                    $oldEventData = $SMS4B->GetLeadData($eventData['ID']);
                    $_SESSION['SMS4B']['LEAD_BUF'][$eventData['ID']] = $oldEventData['STATUS_ID'];
                    return $eventData;
                    break;

                //Для событий контактов
                case 'OnAfterCrmContactAdd':
                    $eventData = array_merge($eventData, $SMS4B->GetContactData($eventData['ID']));
                    break;
                case 'OnAfterCrmContactUpdate':
                    $eventData = array_merge($eventData, $SMS4B->GetContactData($eventData['ID']));
                    break;

                //Для событий сделок
                case 'OnAfterCrmDealAdd':
                    $eventData = array_merge($eventData, $SMS4B->GetDealData($eventData['ID']));
                    break;
                case 'OnAfterCrmDealUpdate':
                    $eventData = array_merge($eventData, $SMS4B->GetDealData($eventData['ID']));
                    if (!empty($_SESSION['SMS4B']['DEAL_BUF'][$eventData['ID']])) {
                        if ($eventData['STAGE_ID'] !== $_SESSION['SMS4B']['DEAL_BUF'][$eventData['ID']]) {
                            $eventData['OLD_STAGE'] = $_SESSION['SMS4B']['DEAL_BUF'][$eventData['ID']];
                            $resolveFlag = 'change_stat_deal_crm';
                        }
                        unset($_SESSION['SMS4B']['DEAL_BUF'][$eventData['ID']]);
                    }
                    break;
                case 'OnAfterCrmDealDelete':
                    $eventData['ID'] = $eventData;
                    break;

                case 'OnBeforeCrmDealUpdate':
                    $oldEventData = $SMS4B->GetDealData($eventData['ID']);
                    $_SESSION['SMS4B']['DEAL_BUF'][$eventData['ID']] = $oldEventData['STAGE_ID'];
                    return $eventData;
                    break;
            }

            //Обработка/подстановка даты
            $eventData = (array)$SMS4B->handleObDate($eventData);

            $sender = $SMS4B->GetCurrentOption('defsender', SITE_ID);

            if ($canSend === 'Y') {
                $text = $SMS4B->GetEventTemplate('SMS4B_' . strtoupper($resolveFlag), SITE_ID);

                $responsible = $eventData['RESPONSIBLE'];

                $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_LOG_GET_PHONE') . $responsible);

                foreach ((array)$eventData as $index => $value) {
                    $text['MESSAGE'] = str_replace('#' . $index . '#', $value, $text['MESSAGE']);
                }
                if (!empty($responsible) && is_array($text)) {
                    $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                        $responsible, $sender, false, false, $eventName);
                }
            }
            if ($canSendAdmin === 'Y') {
                $text = $SMS4B->GetEventTemplate('SMS4B_ADMIN_' . strtoupper($resolveFlag), SITE_ID);
                foreach ((array)$eventData as $index => $value) {
                    $text['MESSAGE'] = str_replace('#' . $index . '#', $value, $text['MESSAGE']);
                }

                $adminPhones = (array)$SMS4B->GetAdminPhones(SITE_ID);
                if (!empty($adminPhones) && is_array($text)) {
                    foreach ($adminPhones as $phoneNum) {
                        $SMS4B->SendSMS($SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                            $phoneNum, $sender, false, false, $eventName);
                    }
                }
            }
            $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_END_CRM_EVENT') . $eventName . PHP_EOL);
        }
    }

    /**
     * Возвращает название источника контакта\лида по его ID
     *
     * @param $id int - ID источника контакта\лида
     *
     * @return mixed - название источника контакта\лида или false
     */
    private function getSourceName($id)
    {
        $arDescSource = CCrmStatus::GetStatusListEx('SOURCE');
        if (!empty($id)) {
            return $arDescSource[$id];
        } else {
            return false;
        }
    }

    /**
     * Возвращает массив с телефонами лида\контакта
     *
     * @param $id int - ID лида\контакта
     *
     * @return array - массив с телефонами
     */
    public function GetPhonesLeadOrContact($id)
    {
        $dbResult = \CCrmFieldMulti::GetList(
            array('ID' => 'asc'),
            array(
                'TYPE_ID' => 'PHONE',
                'ELEMENT_ID' => (int)$id
            )
        );
        $arTempFm = array();
        while ($fields = $dbResult->Fetch()) {
            $arTempFm[$fields['COMPLEX_ID']][] = $fields['VALUE'];
        }

        foreach ($arTempFm as $key => $val) {
            foreach ($val as $key2 => $val2) {
                $data[$key . '_' . ($key2 + 1)] = $val2;
            }
        }

        if (!empty($data)) {
            return $data;
        } else {
            return false;
        }
    }

    /**
     * Перебирает массив и перезаписывает объекты datetime на строковое представление даты-времени
     *
     * @param $data array - входящие данные
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     * @return array - массив с перезаписанными объектами datetime
     */
    private function handleObDate($data)
    {
        foreach ($data as $key => $val) {
            if ($val === 'now()') {
                $val = new \Bitrix\Main\Type\DateTime();
            }
            if (is_object($val) && is_a($val, 'Bitrix\Main\Type\Date')) {
                try {
                    $data[$key] = $val->toString();
                } catch (Exception $e) {
                    $this->sms4bLog(Loc::getMessage('SMS4B_MAIN_ERROR_HANDLE_OB_DATE') . $e->getMessage());
                }
            }
        }

        return $data;
    }

    /**
     * Возвращает название статуса по его ID
     *
     * @param $statusId int - ID статуса
     *
     * @return string - название статуса
     */
    private function getNameStatusId($statusId)
    {
        $arDescStat = CCrmStatus::GetStatusListEx('STATUS');
        return $arDescStat[$statusId];
    }

    /**
     * Возвращает массив с данными лида
     *
     * @param $id int - ID лида
     *
     * @return array - массив с данными лида
     */
    public function GetLeadData($id)
    {
        $result = \Bitrix\Crm\LeadTable::getList(array(
            'select' => array('*'),
            'filter' => array('=ID' => $id)
        ));
        $data = $result->fetch();

        $result = \Bitrix\Crm\ProductRowTable::getList(array(
            'select' => array('IBLOCK_ELEMENT'),
            'filter' => array('=OWNER_ID' => $data['ID'])
        ));

        while ($arProd = $result->fetch()) {
            $data['PRODUCTS'][] = $arProd['CRM_PRODUCT_ROW_IBLOCK_ELEMENT_NAME'];
        }
        $data['PRODUCTS'] = implode(', ', $data['PRODUCTS']);

        $data['STATUS_ID'] = $this->getNameStatusId($data['STATUS_ID']);
        $data['OLD_STAT'] = $this->getNameStatusId($data['OLD_STAT']);

        if (!empty($data['SOURCE_ID'])) {
            $data['SOURCE_ID'] = $this->getSourceName($id);
        }

        $arPhones = $this->GetPhonesLeadOrContact($data['ID']);
        if (!empty($arPhones) && is_array($arPhones)) {
            $data['RESPONSIBLE'] = $this->is_phone(reset($arPhones));
            $data = array_merge($data, $arPhones);
        }

        return $data;
    }

    /**
     * Возвращает массив с данными контакта
     *
     * @param $id int - ID контакта
     *
     * @return array - массив с данными контакта
     */
    public function GetContactData($id)
    {
        $result = \Bitrix\Crm\ContactTable::getList(array(
            'filter' => array('=ID' => $id)
        ));
        $data = $result->Fetch();

        if (!empty($data['SOURCE_ID'])) {
            $data['SOURCE_ID'] = $this->getSourceName($id);
        }

        $arPhones = $this->GetPhonesLeadOrContact($data['ID']);
        if (!empty($arPhones) && is_array($arPhones)) {
            $data['RESPONSIBLE'] = $this->is_phone(reset($arPhones));
            $data = array_merge($data, $arPhones);
        }

        return $data;
    }

    /**
     * Возвращает массив с данными сделки
     *
     * @param $id int - ID сделки
     *
     * @return array - массив с данными сделки
     */
    public function GetDealData($id)
    {
        $result = \Bitrix\Crm\DealTable::getList(array(
            'filter' => array('=ID' => $id)
        ));
        $data = $result->Fetch();

        $arDescDealStage = CCrmStatus::GetStatusListEx('DEAL_STAGE');
        $data['STAGE_ID'] = $arDescDealStage[$data['STAGE_ID']];

        if (!empty($data['OLD_STAGE'])) {
            $data['OLD_STAGE'] = $arDescDealStage[$data['OLD_STAGE']];
        }

        if (!empty($data['CONTACT_ID'])) {
            $arPhones = $this->GetPhonesLeadOrContact($data['CONTACT_ID']);
            if (!empty($arPhones) && is_array($arPhones)) {
                $data['RESPONSIBLE'] = $this->is_phone(reset($arPhones));
                $data = array_merge($data, $arPhones);
            }
        }

        return $data;
    }

    /**
     * Возвращает массив с делами
     *
     * @param $arEventId array - массив с ID дел
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @throws \Bitrix\Main\LoaderException - Исключение подключения модуля
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     * @return array - массив с делами
     */
    public function GetActivityData($arEventId)
    {
        if (!empty($arEventId) && \Bitrix\Main\Loader::includeModule('crm')) {
            global $SMS4B;

            $result = \Bitrix\Crm\ActivityTable::getList(array(
                'select' => array('*'),
                'filter' => array('=ASSOCIATED_ENTITY_ID' => $arEventId)
            ));

            if (is_object($result) && !empty($result)) {
                $data = $result->Fetch();
                $data['PRIORITY'] = CCrmActivityPriority::ResolveDescription($data['PRIORITY']);
                $data['TYPE_ID'] = CCrmActivityType::ResolveDescription($data['TYPE_ID']);
                $data['DIRECTION'] = CCrmActivityDirection::ResolveDescription($data['DIRECTION']);
                $data['RESPONSIBLE'] = $SMS4B->is_phone($this->SearchUserPhone($data['RESPONSIBLE_ID']));

                if (!empty($data['OWNER_ID'])) {
                    $contact = $SMS4B->GetPhonesLeadOrContact($data['OWNER_ID']);
                    $data['CONTACT_PHONE'] = reset($contact);
                }

                return $SMS4B->handleObDate($data);
            } else {
                return false;
            }
        }

        return false;
    }

    /**
     * Перехватчик событий календаря
     *
     * @param $eventName string - Название события
     * @param $resolveFlag string - Название настройки в базе
     * @param $eventData mixed - данные события
     *
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @throws \Bitrix\Main\LoaderException - Исключение подключения модуля
     * @internal param array $params - входные данные
     *
     */
    public function OnRemindEvent($eventName, $resolveFlag, $eventData)
    {
        global $SMS4B;
        $canSend = $SMS4B->GetCurrentOption($resolveFlag, SITE_ID);
        $canSendAdmin = $SMS4B->GetCurrentOption('admin_' . $resolveFlag, SITE_ID);

        if (($canSend === 'Y' || $canSendAdmin === 'Y')
            && $eventData['calType'] === 'user'
            && \Bitrix\Main\Loader::includeModule('calendar')
        ) {
            $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_START_CALENDAR_EVENT') . $eventName);

            $activ = $SMS4B->GetActivityData($eventData['eventId']);
            if (!empty($activ)) {
                if ($canSend === 'Y') {
                    $SMS4B->SendSmsByTemplate(
                        $SMS4B->GetCurrentOption('defsender', SITE_ID),
                        $activ['RESPONSIBLE'],
                        'SMS4B_' . strtoupper($resolveFlag),
                        $activ,
                        $eventName
                    );
                }
                if ($canSendAdmin === 'Y') {
                    $adminPhones = (array)$SMS4B->GetAdminPhones(SITE_ID);
                    if (!empty($adminPhones)) {
                        foreach ($adminPhones as $phoneNum) {
                            $SMS4B->SendSmsByTemplate(
                                $SMS4B->GetCurrentOption('defsender', SITE_ID),
                                $phoneNum,
                                'SMS4B_ADMIN_' . strtoupper($resolveFlag),
                                $activ,
                                $eventName
                            );
                        }
                    }
                }
                $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_SEND_ACTIV'));
            } else {
                $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_EMPTY_ACTIV'));
            }
            $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_END_CALENDAR_EVENT') . $eventName . PHP_EOL);
        }
    }

    /**
     * Отправляет SMS по указанному шаблону
     * @param $sender string - отправитель
     * @param $responsible int - номер получателя
     * @param $templateName string - название шаблона (тип почтового события)
     * @param $eventData array - массив с макросами
     * @param $eventName string - название события
     */
    public function SendSmsByTemplate($sender, $responsible, $templateName, $eventData, $eventName)
    {
        global $SMS4B;
        $text = $SMS4B->GetEventTemplate($templateName, SITE_ID);

        foreach ($eventData as $index => $value) {
            $text['MESSAGE'] = str_replace('#' . $index . '#', $value, $text['MESSAGE']);
        }

        if (!empty($responsible) && !empty($text['MESSAGE'])) {
            $SMS4B->SendSMS(
                $SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                $responsible,
                $sender,
                false,
                false,
                $eventName
            );
        }
    }

    /**
     * Возвращает абсолютный путь к лог-файлу
     *
     * @return string - абсолютный путь к лог-файлу
     */
    public function getLogFileName()
    {
        return $_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/modules/' . GetModuleID(__FILE__) . '/logSms4b.txt';
    }

    /**
     * Возвращает данные из лог-файла
     *
     * @return string - данные из лог-файла, если он был создан
     */
    public function getLogData()
    {
        if (file_exists($this->getLogFileName())) {
            return file_get_contents($this->getLogFileName());
        } else {
            return Loc::getMessage('SMS4B_MAIN_LOG_NO_FILE');
        }
    }

    /**
     * Очистка лог-файла
     */
    public function clLogFile()
    {
        file_put_contents($this->getLogFileName(), '');
    }

    /**
     * Запись в лог
     *
     * @param $data mixed - данные для записи в лог
     *
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     */
    public function sms4bLog($data)
    {
        //global $SMS4B;
        //if ($SMS4B->GetCurrentOption('log_enable', SITE_ID) === 'Y') {
        $dateTime = new \Bitrix\Main\Type\DateTime();
        $data = $dateTime->toString() . ' ' . print_r($data, true) . PHP_EOL;
        file_put_contents($this->getLogFileName(), $data, FILE_APPEND | LOCK_EX);
        //}
    }

    /**
     * Возвращает почтовые шаблоны SMS4B или любые другие по переданному шаблону
     *
     * @param $pattern mixed - требуемый TYPE_ID или шаблон
     * @param $siteId mixed - ID сайта, для которого выбираются шаблоны
     *
     * @return array - массив почтовых шаблоны
     */
    public function GetAllSmsTemplates($pattern = false, $siteId = false)
    {
        $arMess = array();
        $pattern = !empty($pattern) ? $pattern : 'SMS4B_%';
        $arFilter = Array('TYPE_ID' => $pattern);
        if (!empty($siteId)) {
            $arFilter['SITE_ID'] = $siteId;
        }
        $rsMess = CEventMessage::GetList($by = 'id', $order = 'asc', $arFilter);
        while ($res = $rsMess->Fetch()) {
            $arMess[$res['EVENT_NAME']][] = array('ID' => $res['ID'], 'NAME' => $res['SUBJECT']);
        }
        return $arMess;
    }

    /**
     * Возвращает строку с макросами
     *
     * @param $entityType string - тип сущности (Лид\Контакт\Компания)
     * @param $needUf bool - влаг добавления пользовательских полей в строку с макросами
     *
     * @return string/bool - строка с макросами\false
     */
    public function GetMacros($entityType, $needUf = false)
    {
        if (!CModule::IncludeModule('crm')) {
            return false;
        }
        $arMacros = array();
        $dbResult = \CCrmFieldMulti::GetList(
            array('ID' => 'asc'),
            array(
                'TYPE_ID' => 'PHONE',
                'ENTITY_ID' => $entityType
            )
        );
        while ($fields = $dbResult->Fetch()) {
            $arMacros[] = $fields['COMPLEX_ID'];
        }
        $macrString = "\n";
        foreach (array_unique($arMacros) as $val) {
            $macrString .= "#$val" . '_1# - ' . Loc::getMessage($val) . "_1\n";
            $macrString .= "#$val" . '_2# - ' . Loc::getMessage($val) . "_2\n";
        }

        if (!empty($needUf)) {
            global $USER_FIELD_MANAGER;
            $fields = $USER_FIELD_MANAGER->GetUserFields('CRM_' . $entityType);

            foreach ($fields as $val) {
                $desc = CAllUserTypeEntity::GetByID($val['ID']);
                $macrString .= '#' . $val['FIELD_NAME'] . '# - ' . $desc['EDIT_FORM_LABEL']['ru'] . "\n";
            }
        }

        return $macrString;
    }

    /**
     * Перехватчик deadline по задачам
     *
     * @throws \Bitrix\Main\LoaderException - Исключение подключения модуля
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     * @return string - вызов самого себя (для агента)
     */
    public function TaskDeadline()
    {
        global $SMS4B;

        if (\Bitrix\Main\Loader::includeModule('tasks')
            && $SMS4B->GetCurrentOption('intercept_deadline', SITE_ID) === 'Y'
        ) {
            $lastDead = $SMS4B->GetCurrentOption('deadline_date', SITE_ID);
            $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_START_SEARCH_DEADLINE') . \Bitrix\Main\Type\DateTime::createFromTimestamp($lastDead)->toString());
            $sender = $SMS4B->GetCurrentOption('defsender', SITE_ID);

            if (empty($lastDead)) {
                COption::SetOptionString('rarus.sms4b', 'deadline_date', time());
                $lastDead = time();
            }
            $obLastDead = \Bitrix\Main\Type\DateTime::createFromTimestamp($lastDead);

            $result = \Bitrix\Tasks\TaskTable::getList(array(
                'select' => array('*'),
                'filter' => array(
                    '>DEADLINE' => $obLastDead,
                    '<DEADLINE' => \Bitrix\Main\Type\DateTime::createFromTimestamp(time()),
                    'CLOSED_DATE' => null,
                    '=ZOMBIE' => 'N'
                )
            ));

            while ($res = $result->Fetch()) {
                if ($SMS4B->checkGroupPerm($res['GROUP_ID'])) {
                    $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_SEARCH_DEADLINE_STEP') . '"' . $res['TITLE'] . '"');
                    $text = $SMS4B->GetEventTemplate('SMS4B_TASK_INTERCEPT_DEADLINE', SITE_ID);

                    $responsible = $SMS4B->is_phone($SMS4B->SearchUserPhone($res['RESPONSIBLE_ID']));

                    $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_RESPONS_ID') . $res['RESPONSIBLE_ID']);
                    $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_LOG_GET_PHONE') . $responsible);

                    foreach ($res as $index => $value) {
                        $text['MESSAGE'] = str_replace('#' . $index . '#', $value, $text['MESSAGE']);
                    }

                    if (!empty($responsible) && is_array($text)) {
                        $SMS4B->SendSMS(
                            $SMS4B->use_translit === 'Y' ? $SMS4B->Translit($text['MESSAGE']) : $text['MESSAGE'],
                            $responsible,
                            $sender,
                            false,
                            false,
                            'Agent'
                        );

                        COption::SetOptionString('rarus.sms4b', 'deadline_date', strtotime($res['DEADLINE']) + 1);
                    }
                }
            }

            if (empty($text)) {
                $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_SEARCH_DEADLINE_EMPTY'));
            }

            $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_END_SEARCH_DEADLINE') . PHP_EOL);
        }
        return 'CSms4BitrixWrapper::TaskDeadline();';
    }

    /**
     * Возвращает все рабочие группы
     *
     * @throws \Bitrix\Main\LoaderException - Исключение подключения модуля
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @return array - массив данных рабочих групп
     */
    public function GetSonetGroups()
    {
        $arWorkGroups = array();
        if (\Bitrix\Main\Loader::includeModule('socialnetwork')) {
            $result = Bitrix\Socialnetwork\WorkgroupTable::getList(array(
                'select' => array('ID', 'NAME')
            ));
            $arWorkGroups[] = array('ID' => 'NO_GROUP', 'NAME' => Loc::getMessage('SMS4B_MAIN_TASKS_NO_GROUP'));
            while ($res = $result->fetch()) {
                $arWorkGroups[] = $res;
            }
        }
        return $arWorkGroups;
    }

    /**
     * Разрешена ли отправка группам
     *
     * @param string - ID группы
     *
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @return bool - результат проверки
     */
    public function checkGroupPerm($groupId)
    {
        $arSonetGroups = $this->GetPermGroups();

        if (in_array($groupId, $arSonetGroups, false) || (empty($groupId) && in_array('NO_GROUP', $arSonetGroups,
                    false))
        ) {
            return true;
        } else {
            return false;
        }

    }

    /**
     * Возвращает те рабочие группы, для которых разрешена отправка
     *
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @return array - массив с ID рабочих групп, для которых разрешена отправка
     */
    public function GetPermGroups()
    {
        return (array)unserialize($this->GetCurrentOption('serialize_work_groups', SITE_ID));
    }

    /**
     * Добавляет настройки отправки СМС в список заказов\отображает результат отправки
     *
     * @param $list link - ссылка на объект класса CAdminList
     */
    public function OnAdminListDisplayHandler(&$list)
    {
        if (!IsModuleInstalled('rarus.sms4b')) {
            return false;
        }
        global $SMS4B;

        if (self::CheckSaleOrderPage()) {
            $arDefaultTemplates = $SMS4B->GetAllSmsTemplates('SMS4B_SALE_%', $_REQUEST['filter_lang'] ?: false);
            $arCustomTemplates = $SMS4B->GetAllSmsTemplates('SMS4B_USER_CUSTOM_EVENT',
                $_REQUEST['filter_lang'] ?: false);
            $customTemplates = $arCustomTemplates['SMS4B_USER_CUSTOM_EVENT'];
        } elseif (self::CheckUserAdminPage()) {
            $arCustomTemplates = $SMS4B->GetAllSmsTemplates('SMS4B_USER_LIST_CUSTOM_EVENT');
            $customTemplates = $arCustomTemplates['SMS4B_USER_LIST_CUSTOM_EVENT'];
        } else {
            return false;
        }

        $html = self::GetSelectHtmlOnAdminList($arDefaultTemplates, $customTemplates);

        $list->arActions = array_merge(array('sms4b_send_sms' => Loc::getMessage('SMS4B_MAIN_ORDER_LIST_MENU_SEND_SMS_BRAND')),
            $list->arActions);
        $list->arActions['sms4b_send_sms_list'] = array('type' => 'html', 'value' => $html);

        $list->arActionsParams['select_onchange'] .= "
            BX('sms4b_send_sms_list').style.display = (this.value == 'sms4b_send_sms' ? 'block':'none');
            if(this.value == 'sms4b_send_sms')
            {
                BX('form_tbl_sale_order').elements['apply'].value = '" . Loc::getMessage('SMS4B_MAIN_ORDER_LIST_MENU_SEND_SMS') . "';
            }
            else
            {
                BX('form_tbl_sale_order').elements['apply'].value = '" . Loc::getMessage('SMS4B_MAIN_ORDER_LIST_MENU_APPLY') . "';
            }
        ";

        $result = $_POST['SMS4B_SEND_RESULT'];
        if ($result['RESULT'] === true) {
            $list->arActionSuccess[] = $result['DESCRIPTION'];
        } else {
            $list->arGroupErrors[] = array($result['DESCRIPTION']);
        }
    }

    /**
     * Генерирует select-ы для отправки из списков
     *
     * @param array $arDefaultTemplates - массив шаблонов по-умолчанию
     * @param array $arCustomTemplates - массив пользовательских шаблонов
     *
     * @return string - html select-а
     */
    private static function GetSelectHtmlOnAdminList($arDefaultTemplates = array(), $arCustomTemplates = array())
    {
        $html = '<select style="display:none" id="sms4b_send_sms_list" name="sms4b_send_sms_list">';
        $html .= "<option value=''>" . Loc::getMessage('SMS4B_MAIN_ORDER_LIST_MENU_TEMPLATES') . '</option>';
        if (!empty($arDefaultTemplates)) {
            $html .= "<option value=''>" . Loc::getMessage('SMS4B_MAIN_ORDER_LIST_MENU_TEMPLATES_DEFAULT') . '</option>';
            foreach ($arDefaultTemplates as $template) {
                $html .= "<option value='" . $template[0]['ID'] . "'>" . $template[0]['NAME'] . '</option>';
            }
        }

        if (!empty($arCustomTemplates)) {
            $html .= "<option value=''>" . Loc::getMessage('SMS4B_MAIN_ORDER_LIST_MENU_TEMPLATES_CUSTOM') . '</option>';
            foreach ($arCustomTemplates as $template) {
                $html .= "<option value='" . $template['ID'] . "'>" . $template['NAME'] . '</option>';
            }
        }
        $html .= '</select>';

        return $html;
    }

    /**
     * Обработчик OnBeforeProlog - отправляет сообщения из списка заказов
     *
     * @throws \Bitrix\Main\LoaderException - Исключение подключения модуля
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     */
    public function OnBeforePrologHandler()
    {
        $request = Application::getInstance()->getContext()->getRequest();
        $sms4bSendSmsList = $request->getPost('sms4b_send_sms_list');

        if ($request->getRequestMethod() === 'POST'
            && $request->getPost('action') === 'sms4b_send_sms'
            && is_array($request->getPost('ID'))
            && Bitrix\Main\Loader::includeModule('rarus.sms4b')
            && check_bitrix_sessid()
            && ((Bitrix\Main\Loader::includeModule('sale') && self::CheckSaleOrderPage()) || self::CheckUserAdminPage())
        ) {
            global $SMS4B;
            if (!empty($sms4bSendSmsList)) {
                $arSend = array();

                $text = $SMS4B->GetEventTemplateFromId($request->getPost('sms4b_send_sms_list'));

                if (self::CheckSaleOrderPage()) {
                    $arData = (array)$SMS4B->GetOrderData($request->getPost('ID'));
                } else {
                    $arData = (array)$SMS4B->GetUserData($request->getPost('ID'));
                }

                foreach ($arData as $item) {
                    $cleanPhone = (string)$SMS4B->is_phone($item['PHONE_TO']);
                    if (!empty($cleanPhone)) {
                        $arSend[$cleanPhone] = $text;
                        foreach ($item as $k => $orderProps) {
                            $arSend[$cleanPhone] = str_replace('#' . $k . '#', $orderProps,
                                $arSend[$cleanPhone]);
                        }
                    }
                }
                try {
                    $sendResult = $SMS4B->SendSmsSaveGroup($arSend, '', '', '', '', '', '', 'SMS4B_USER_CUSTOM_EVENT');

                    $_POST['SMS4B_SEND_RESULT'] = self::GetResultDescription($sendResult);

                } catch (\Rarus\Sms4b\Sms4bException $e) {
                    $_POST['SMS4B_SEND_RESULT'] = array('RESULT' => false, 'DESCRIPTION' => $e->getMessage());
                }
            } else {
                $_POST['SMS4B_SEND_RESULT'] = array(
                    'RESULT' => false,
                    'DESCRIPTION' => Loc::getMessage('SMS4B_MAIN_ORDER_LIST_ERROR_EMPTY_TEMPLATE')
                );
            }
        }
    }

    /**
     * Обработка результатов отправки для CAdminMessage (без верстки)
     * @param $sendResult array - массив с отправками
     *
     * @return array - массив вида array('RESULT' => ..., 'DESCRIPTION' => ...);
     */
    public static function GetResultDescription($sendResult)
    {
        $sendPreview = $failSendPreview = '';
        $successSend = $failSend = $moreNumbers = $moreFailedNumbers = 0;
        foreach ($sendResult as $smsRes) {
            if ($smsRes['Result'] > 0) {
                if ($successSend < self::RESULT_ON_ORDER_LIST) {
                    $sendPreview .= Loc::getMessage('SMS4B_MAIN_ORDER_LIST_SEND_SMS_FROM_NUM') . $smsRes['Destination']
                        . Loc::getMessage('SMS4B_MAIN_ORDER_LIST_SEND_SMS_FROM_NAME') . $smsRes['SenderName']
                        . Loc::getMessage('SMS4B_MAIN_ORDER_LIST_SEND_SMS_WITH_TEXT') . '"' . $smsRes['TextMessage'] . '"<br>';
                    $moreNumbers++;
                }
                $successSend++;
            } else {
                if ($failSend < self::RESULT_ON_ORDER_LIST) {
                    $failSendPreview .= Loc::getMessage('SMS4B_MAIN_ORDER_LIST_NOT_SEND_SMS_FROM_NUM') . $smsRes['Destination']
                        . Loc::getMessage('SMS4B_MAIN_ORDER_LIST_SEND_SMS_FROM_NAME') . $smsRes['SenderName']
                        . Loc::getMessage('SMS4B_MAIN_ORDER_LIST_SEND_SMS_WITH_TEXT') . '"' . $smsRes['TextMessage'] . '"<br>';
                    $moreFailedNumbers++;
                }
                $failSend++;
            }
        }

        if ($failSend === 0) {
            $result['DESCRIPTION'] = $sendPreview;

            if ($successSend > self::RESULT_ON_ORDER_LIST) {
                $lastNum = $successSend - $moreNumbers;
                $lastDeclNum = self::GetDeclNum($lastNum, array(
                    Loc::getMessage('SMS4B_MAIN_ORDER_LIST_NUMBERS_1'),
                    Loc::getMessage('SMS4B_MAIN_ORDER_LIST_NUMBERS_234'),
                    Loc::getMessage('SMS4B_MAIN_ORDER_LIST_NUMBERS_OTHER')
                ));

                $result['DESCRIPTION'] .= Loc::getMessage('SMS4B_MAIN_ORDER_LIST_MORE_NUM') . " $lastNum $lastDeclNum" . '<br>';
            }

            $result['RESULT'] = true;
        } else {
            if ($successSend > 0) {
                $result['DESCRIPTION'] = Loc::getMessage('SMS4B_MAIN_ORDER_LIST_SEND') . " $successSend "
                    . Loc::getMessage('SMS4B_MAIN_ORDER_LIST_SEPARATOR') . count($sendResult) . ' '
                    . Loc::getMessage('SMS4B_MAIN_ORDER_LIST_SMS') . '<br><br>' . $failSendPreview;
                if ($failSend > self::RESULT_ON_ORDER_LIST) {
                    $lastNum = $failSend - $moreFailedNumbers;
                    $lastDeclNum = self::GetDeclNum($lastNum, array(
                        Loc::getMessage('SMS4B_MAIN_ORDER_LIST_NUMBERS_1'),
                        Loc::getMessage('SMS4B_MAIN_ORDER_LIST_NUMBERS_234'),
                        Loc::getMessage('SMS4B_MAIN_ORDER_LIST_NUMBERS_OTHER')
                    ));

                    $result['DESCRIPTION'] .= Loc::getMessage('SMS4B_MAIN_ORDER_LIST_MORE_NUM') . " $lastNum $lastDeclNum" . '<br>';
                }
            } else {
                $result['DESCRIPTION'] = Loc::getMessage('SMS4B_MAIN_ORDER_LIST_ALL_FAIL');
            }

            $result['RESULT'] = false;
        }

        $result['DESCRIPTION'] .= '<br>' . Loc::getMessage('SMS4B_MAIN_ORDER_LIST_REPORT_LINK');

        return $result;
    }

    /**
     * Проверка - выполняется ли скрипт на странице заказов
     *
     * @return bool - результат проверки
     */
    public static function CheckSaleOrderPage()
    {
        if ($GLOBALS['APPLICATION']->GetCurPage() === '/bitrix/admin/sale_order.php') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Проверка - выполняется ли скрипт на странице пользователей
     *
     * @return bool - результат проверки
     */
    public static function CheckUserAdminPage()
    {
        if ($GLOBALS['APPLICATION']->GetCurPage() === '/bitrix/admin/user_admin.php') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Возврат окончания слова при склонении
     *
     * Функция возвращает окончание слова, в зависимости от примененного к ней числа
     * Например: 5 товаров, 1 товар, 3 товара
     *
     * @param int $value - число, к которому необходимо применить склонение
     * @param array $status - массив возможных окончаний
     * @return mixed
     */
    public static function GetDeclNum($value, $status)
    {
        $array = array(2, 0, 1, 1, 1, 2);
        return $status[($value % 100 > 4 && $value % 100 < 20) ? 2 : $array[($value % 10 < 5) ? $value % 10 : 5]];
    }

    /**
     * Возвращает данные по заказам (цена, состав заказа, основные свойства)
     *
     * #ORDER_ID# - код заказа
     * #ORDER_DATE# - дата заказа
     * #ORDER_USER# - заказчик
     * #PRICE# - сумма заказа
     * #PHONE_TO# - телефон заказчика
     * #ORDER_LIST# - состав заказа
     * #SALE_PHONE# - телефон отдела продаж
     *
     * @param $arIds array - массив с ID заказов
     *
     * @throws \Bitrix\Main\LoaderException - Исключение подключения модуля
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     * @return array - данные по заказам
     */
    public function GetOrderData($arIds)
    {
        $arResult = array();
        if (Bitrix\Main\Loader::includeModule('sale') && Bitrix\Main\Loader::includeModule('rarus.sms4b')) {
            global $SMS4B;
            if (!is_array($arIds)) {
                $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_ORDER_LIST_ERROR_EMPTY_ORDER_LIST'));
                return $arResult;
            }

            //Основные свойства заказа
            $obRes = Bitrix\Sale\OrderTable::getList(array(
                'select' => array('*'),
                'filter' => array('ID' => $arIds)
            ));

            while ($arOrder = $obRes->Fetch()) {
                $arResult[$arOrder['ID']] = $this->handleObDate($arOrder);
                $arResult[$arOrder['ID']]['ORDER_DATE'] = $arOrder['DATE_INSERT']->format(\Bitrix\Main\Type\Date::getFormat());
            }

            //Дополнительные свойства заказа (по типам плательщика)
            $obRes = CSaleOrderPropsValue::GetList(array('ID' => 'ASC'), array('ORDER_ID' => $arIds));
            $phoneCode = $SMS4B->GetCurrentOption('phone_number_code');
            while ($arOrder = $obRes->Fetch()) {
                if ($phoneCode !== $arOrder['CODE']) {
                    $arResult[$arOrder['ORDER_ID']][$arOrder['CODE']] = $arOrder['VALUE'];
                } else {
                    $arResult[$arOrder['ORDER_ID']]['PHONE_TO'] = $arOrder['VALUE'];
                }
            }

            //Состав корзины
            $obRes = Bitrix\Sale\Basket::getList(array(
                'select' => array('ORDER_ID', 'DATE_INSERT', 'NAME'),
                'filter' => array('ORDER_ID' => $arIds)
            ));

            while ($arOrder = $obRes->Fetch()) {
                if (array_key_exists($arOrder['ORDER_ID'], $arResult)) {
                    $arResult[$arOrder['ORDER_ID']]['ORDER_LIST'] .= !empty($arResult[$arOrder['ORDER_ID']]['ORDER_LIST']) ? ', ' . $arOrder['NAME'] : $arOrder['NAME'];
                }
            }
        }

        return $arResult;
    }

    /**
     * Возвращает данные по пользователям
     *
     * @param $arIds mixed - ID или массив с ID пользователей
     * @throws \Bitrix\Main\ArgumentException - Исключения ORM
     * @throws \Bitrix\Main\LoaderException - Исключения ORM
     *
     * @return array - данные по пользователю
     */
    public function GetUserData($arIds)
    {
        $arRes = array();
        $userPhoneCode = $this->GetCurrentOption('user_property_phone');

        if (!empty($arIds)) {
            $rsUser = CUser::GetList($by = 'ID', $order = 'desc', array('ID' => implode('|', (array)$arIds)),
                array('SELECT' => array('UF_*')));
            while ($res = $rsUser->Fetch()) {
                $res['PHONE_TO'] = $res[$userPhoneCode];
                $arRes[] = $res;
            }
        }
        return $arRes;
    }

    /**
     * Возвращает все типы плательщиков
     *
     * @throws \Bitrix\Main\LoaderException - Исключение подключения модуля
     * @return array - типы плательщиков
     */
    public function GetPersonTypes()
    {
        $person = array();
        if (Bitrix\Main\Loader::includeModule('sale')) {
            $person = array();
            $pType = Bitrix\Sale\PersonTypeTable::getList(array(
                'select' => array('ID', 'NAME')
            ));

            while ($type = $pType->Fetch()) {
                $person[$type['ID']] = $type['NAME'];
            }
        }
        return $person;
    }

    /**
     * Возвращает свойства заказа
     *
     * @throws \Bitrix\Main\LoaderException - Исключение подключения модуля
     * @return array - свойства заказа
     */
    public function GetSaleOrderProps()
    {
        $orderProps = array();
        if (Bitrix\Main\Loader::includeModule('sale')) {
            $obRes = CSaleOrderProps::GetList(
                array('SORT' => 'ASC'),
                array(),
                false,
                false,
                array('NAME', 'CODE', 'PERSON_TYPE_ID')
            );
            $orderProps = array();
            while ($props = $obRes->Fetch()) {
                $orderProps[$props['CODE']][] = $props;
            }
        }
        return $orderProps;
    }

    /**
     * Получить массив с часовыми поясами
     *
     * @author azarev
     * @return array массив с часовыми поясами формата [часы] => [(+часы) Область]
     *
     */
    public static function getTimeZone()
    {
        return array(
            3 => '(+3) ' . Loc::getMessage('SMS4B_MAIN_TIMEZONE_MOSCOW'),
            2 => '(+2) ' . Loc::getMessage('SMS4B_MAIN_TIMEZONE_KALININGRAD'),
            4 => '(+4) ' . Loc::getMessage('SMS4B_MAIN_TIMEZONE_SAMARA'),
            5 => '(+5) ' . Loc::getMessage('SMS4B_MAIN_TIMEZONE_EKATA'),
            6 => '(+6) ' . Loc::getMessage('SMS4B_MAIN_TIMEZONE_OMSK'),
            7 => '(+7) ' . Loc::getMessage('SMS4B_MAIN_TIMEZONE_KRASNOYARSK'),
            8 => '(+8) ' . Loc::getMessage('SMS4B_MAIN_TIMEZONE_IRKYTSK'),
            9 => '(+9) ' . Loc::getMessage('SMS4B_MAIN_TIMEZONE_YAKUTSK'),
            10 => '(+10) ' . Loc::getMessage('SMS4B_MAIN_TIMEZONE_VLADIVOSTOK'),
            11 => '(+11) ' . Loc::getMessage('SMS4B_MAIN_TIMEZONE_CHOKURDAH'),
            12 => '(+12) ' . Loc::getMessage('SMS4B_MAIN_TIMEZONE_KAMCHATKA')
        );
    }

    /**
     * Автоответчик
     *
     * @param $eventData array - данные события
     *
     * @throws \Bitrix\Main\LoaderException - Исключение подключения модуля
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     *
     * @return bool - false в случае неудачного подключения модуля SMS4B
     */
    public function AutoAnswering($eventData)
    {
        if (Bitrix\Main\Loader::includeModule('rarus.sms4b')) {
            global $SMS4B;
            $autoAnsweringFlag = $SMS4B->GetCurrentOption('event_autoanswer', SITE_ID);
            $missedCallFlag = $SMS4B->GetCurrentOption('event_missed_call', SITE_ID);
        } else {
            return false;
        }

        if (($autoAnsweringFlag === 'Y' || $missedCallFlag === 'Y')
            && ($eventData['CALL_TYPE'] === CVoxImplantMain::CALL_INCOMING || $eventData['CALL_TYPE']
                === CVoxImplantMain::CALL_INCOMING_REDIRECT)
            && Bitrix\Main\Loader::includeModule('voximplant')
        ) {
            $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_START_AUTOANSWER'));
            //форматируем объекты дат в строки
            $eventData = $SMS4B->handleObDate(CVoxImplantHistory::PrepereData($eventData));
            //добавляем данные, пользователя, которому звонили
            $userData = $SMS4B->GetUserData(array($eventData['PORTAL_USER_ID']));
            $eventData = array_merge($eventData, $userData[0]);

            //формируем массивы $arSearch и $arReplace для замены макросов
            $eventData['CALL_FAILED_REASON'] = Loc::getMessage('VI_STATUS_' . $eventData['CALL_FAILED_CODE']);
            foreach ($eventData as $key => $val) {
                $arSearch[] = '#' . $key . '#';
                $arReplace[] = $val;
            }

            if ($autoAnsweringFlag === 'Y') {
                $callerPhone = $eventData['PHONE_NUMBER'];
                $textTemplate = $SMS4B->GetEventTemplate('SMS4B_AUTOANSWER', SITE_ID);
                $arPhonesMessages[$callerPhone] = str_replace($arSearch, $arReplace, $textTemplate['MESSAGE']);
            }

            if ($missedCallFlag === 'Y') {
                $portalUserPhone = $SMS4B->SearchUserPhone($eventData['PORTAL_USER_ID']);
                $textTemplate = $SMS4B->GetEventTemplate('SMS4B_MISSED_CALL', SITE_ID);
                $arPhonesMessages[$portalUserPhone] = str_replace($arSearch, $arReplace, $textTemplate['MESSAGE']);
            }

            if (!empty($arPhonesMessages)) {
                try {
                    $sender = $SMS4B->GetCurrentOption('defsender', SITE_ID);
                    $res = $SMS4B->SendSmsSaveGroup($arPhonesMessages, $sender, '', '', '', '', '', 'SMS4B_AUTOANSWER');
                    $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_AUTOANSWER_RESULT') . count($res));
                } catch (Sms4bException $e) {
                    $SMS4B->sms4bLog($e->getMessage());
                }
            }
            $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_STOP_AUTOANSWER') . PHP_EOL);
        }
    }

    /**
     * Возвращает массив статусов магазина определенного типа
     *
     * @param string $type - тип статуса ('O' - заказ, 'D' - отгрузка)
     * @param string $lid - лид
     *
     * @throws \Bitrix\Main\LoaderException - Исключение подключения модуля
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     *
     * @return array - массив статусов
     */
    public function GetSaleStatus($type = '', $lid = 'ru')
    {
        $arSt = $arFilter = array();
        if (!Bitrix\Main\Loader::includeModule('sale')) {
            return $arSt;
        }

        if (!empty($type)) {
            $arFilter = array('TYPE' => $type);
        }

        $result = StatusTable::getList(array(
            'select' => array('ID', 'TYPE'),
            'filter' => $arFilter
        ));
        while ($row = $result->fetch()) {
            $tmpSt[] = $row['ID'];
            $arSt[$row['ID']] = $row;
        }

        $result = StatusLangTable::getList(array(
            'select' => array('*'),
            'filter' => array('LID' => $lid, 'STATUS_ID' => $tmpSt)
        ));

        while ($row = $result->fetch()) {
            $arSt[$row['STATUS_ID']]['NAME'] = $row['NAME'];
        }
        return $arSt;
    }

    /**
     * Обработчик смены статуса отгрузки
     *
     * @param object $obEventData - параметры отгрузки
     *
     * @throws \Bitrix\Main\LoaderException - Исключение подключения модуля
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     *
     */
    public function SaleShipmentHandler($obEventData)
    {
        if (Bitrix\Main\Loader::includeModule('rarus.sms4b') && Bitrix\Main\Loader::includeModule('sale')) {
            global $SMS4B;

            $newSt = $obEventData->getField('STATUS_ID');
            $result = ShipmentTable::getList(array(
                'select' => array('STATUS_ID'),
                'filter' => array('ID' => $obEventData->getField('ID'))
            ));
            $currentSt = $result->fetch();

            if ($newSt !== $currentSt['STATUS_ID']) {
                $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_START_SHIPMENT_EVENT'));

                $arEventFields = (array)$SMS4B->handleObDate($obEventData->getFieldValues());
                $arDeliverySt = $SMS4B->GetSaleStatus('D');
                $arEventFields['STATUS_NAME'] = $arDeliverySt[$newSt]['NAME'];
                $orderLid = $SMS4B->GetOrderLid($arEventFields['ORDER_ID']);
                $text = $SMS4B->GetEventTemplate('SMS4B_SALE_STATUS_CHANGED_' . $newSt, $orderLid);
                $sender = $SMS4B->GetCurrentOption('defsender', $orderLid);
                $orderPhone = $SMS4B->is_phone($SMS4B->GetPhoneOrder($arEventFields['ORDER_ID'], SITE_ID));

                foreach ($arEventFields as $index => $value) {
                    $text['MESSAGE'] = str_replace('#' . $index . '#', $value, $text['MESSAGE']);
                }

                if ($SMS4B->GetCurrentOption("event_sale_status_$newSt", $orderLid) === 'Y') {
                    try {
                        $SMS4B->SendSmsSaveGroup(array($orderPhone => $text['MESSAGE']),
                            $sender, '', '', '', '', $arEventFields['ORDER_ID'], __FUNCTION__, '');
                        $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_SEND_SHIPMENT_NUM') . $orderPhone
                            . Loc::getMessage('SMS4B_MAIN_SEND_SHIPMENT_TEXT') . $text['MESSAGE']);
                    } catch (Sms4bException $e) {
                        $SMS4B->sms4bLog($e->getMessage());
                    }
                }
            }

            $SMS4B->sms4bLog(Loc::getMessage('SMS4B_MAIN_END_SHIPMENT_EVENT') . PHP_EOL);
        }
    }

    /**
     * Возвращает ID сайта, на котором был сделан заказ
     *
     * @param int $id - id заказа
     *
     * @throws \Bitrix\Main\LoaderException - Исключение подключения модуля
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     *
     * @return string - ID сайта
     */
    public function GetOrderLid($id)
    {
        if (!Bitrix\Main\Loader::includeModule('sale')) {
            return false;
        }
        $obRes = Bitrix\Sale\Internals\OrderTable::getList(array(
            'select' => array('LID'),
            'filter' => array('ID' => $id)
        ));
        $arResult = $obRes->fetch();

        return $arResult['LID'];
    }

}
