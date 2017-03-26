<?
use \Bitrix\Main\Localization\Loc;

Loc::loadLanguageFile(__FILE__);

/**
 * Базовый класс SMS4B
 */
class CSms4bBase
{
    /**
     * @const PHONE_PATTERN string - шаблон телефонного номера
     */
    const PHONE_PATTERN = "/^[+]?[78]?[(]?([93]{1}\d{2})[)]?(\d{7})$/";
    /**
     * @var $header string - заголовок xml
     */
    protected $header = 'POST %addr% HTTP/1.1
Host: sms4b.ru
Content-Type: text/xml; charset=utf-8
Cache-Control: no-cache, must-revalidate
Pragma: no-cache
Content-Length: %lenght%
SOAPAction: "SMS %nameclient%/%func%"

';
    /**
     * @var $arrHeader array - массив заголовка xml
     */
    protected static $arrHeader = array(
        'Content-Type' => 'Content-Type: text/xml; charset=utf-8',
        'CacheControl' => 'Cache-Control: no-cache, must-revalidate',
        'Pragma' => 'Pragma: no-cache',
        'ContentLength' => 'Content-Length: %lenght%',
        'SOAPAction' => 'SOAPAction: "SMS %nameclient%/%func%"'
    );
    /**
     * @var $MESSAGE_ENCODING_GSM int - код кодировки GSM 03.38
     */
    const MESSAGE_ENCODING_GSM = 0;
    /**
     * @var $MESSAGE_ENCODING_UNICODE int - код кодировки UNICODE
     */
    const MESSAGE_ENCODING_UNICODE = 1;
    /**
     * @var $defAddr string - относительный адрес сервиса по-умолчанию
     */
    protected $defAddr = '/webservices/sms.asmx';
    /**
     * @var $defClient string - название клиента по-умолчанию
     */
    protected $defClient = 'client';
    /**
     * @var $xml_header string - заголовок xml
     */
    protected $xml_header = '<?xml version="1.0" encoding="utf-8"?>
<soap:Envelope xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xsd="http://www.w3.org/2001/XMLSchema" xmlns:soap="http://schemas.xmlsoap.org/soap/envelope/">
<soap:Body>
';
    /**
     * @var $xml_footer string - заключительная часть xml
     */
    protected $xml_footer = '</soap:Body>
</soap:Envelope>';
    /**
     * @var $serv_addr string - адрес сервиса
     */
    protected $serv_addr = 'https://sms4b.ru';
    /**
     * @var $serv_port int - номер порта
     */
    protected $serv_port = 443;
    /**
     * @var $proxy_serv_addr string - адрес прокси
     */
    protected $proxy_serv_addr = '';
    /**
     * @var $proxy_serv_port string - порт прокси
     */
    protected $proxy_serv_port = '';
    /**
     * @var $proxy_use bool - флаг использования прокси
     */
    protected $proxy_use = false;
    /**
     * @var $LastError string - буффер, в который пишется последняя возникшая ошибка
     */
    public $LastError = '';
    /**
     * @var $LastReq string - буффер, в который пишется последний xml-запрос к сервису
     */
    public $LastReq = '';
    /**
     * @var $LastRes string - буффер, в который пишется последний xml-ответ от сервиса
     */
    public $LastRes = '';
    /**
     * @var $arBalance array - массив с данными баланса пользователя
     */
    public $arBalance = array();
    /**
     * @var $sms_sym_count string - максимальное количество символов с смс
     */
    public $sms_sym_count = '';
    /**
     * @var $login string - логин для сервиса
     */
    protected $login = '';
    /**
     * @var $version string - версия скрипта
     */
    protected $version = 'p';
    /**
     * @var $password string - пароль для сервиса
     */
    protected $password = '';
    /**
     * @var $gmt string - часовой пояс
     */
    protected $gmt = '';
    /**
     * @var $sid int - SID
     */
    protected $sid = 0;
    /**
     * @var $DefSender string - отправитель по-умолчанию
     */
    protected $DefSender = '';
    /**
     * @var $use_translit bool - флаг использования транслитерации
     */
    public $use_translit = false;
    /**
     * @var $inc_date string - дата последнего входящего сообщения
     */
    protected $inc_date = '';
    /**
     * @var $maxPackage int - максимальное количество сообщений в одной отправке
     */
    public $maxPackage = 100;
    /**
     * @var $maxPackage int - максимальное количество сообщений в одной отправке
     */

    /**
     * Конструктор
     * @param string $login - Логин
     * @param string $password - Пароль
     */
    public function __construct($login = '', $password = '')
    {
        session_start();
        $this->login = ' ' . $this->version . ' ' . $login;
        $this->password = $password;
        $this->gmt = '3';
        $this->serv_addr = 'https://sms4b.ru';
        $this->UpdateSID();
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
    protected function getbodyrec($funcname = '', array $param = array(), $nameclient)
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
                    if ($funcname === 'GroupSMS' && $name === 'List') {
                        $head_schema = '<List>' . "\r\n";
                        $sms = $val;

                        $i = 1;
                        $bodyrec .= $head_schema;
                        foreach ($sms as $key => $value) {
                            $bodyrec .= '<GroupSMSList>' . "\r\n";
                            $bodyrec = str_replace('%table_num%', $i, $bodyrec);
                            $i++;
                            foreach ($value as $xml_tag_name => $xml_tag_value) {
                                $bodyrec .= '<' . $xml_tag_name . '>' . $xml_tag_value . '</' . $xml_tag_name . '>' . "\r\n";
                            }
                            $bodyrec .= '</GroupSMSList>' . "\r\n";
                        }
                        $bodyrec .= '</List>' . "\r\n";
                    } else {
                        $bodyrec .= '<' . $name . '>' . $val . '</' . $name . '>' . "\r\n";
                    }
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
     * @param $nameclient string - название клиента
     * @param $address string - относительный адрес сервиса
     *
     * @return mixed - распарсенный ответ/false
     */
    protected function makeRequest($funcname, $param, $nameclient, $address)
    {
        $xml = $this->getbodyrec($funcname, $param, $nameclient);
        $xmllen = strlen($xml);
        $arrHeader = self::$arrHeader;

        $arrHeader['ContentLength'] = str_replace('%lenght%', $xmllen, $arrHeader['ContentLength']);
        $arrHeader['SOAPAction'] = str_replace('%nameclient%', $nameclient, $arrHeader['SOAPAction']);
        $arrHeader['SOAPAction'] = str_replace('%func%', $funcname, $arrHeader['SOAPAction']);

        //@TODO хак, убрать как только перейдем на использование SOAP-клиента
        if (!function_exists('curl_exec')) {
            echo '<a href="http://php.net/manual/ru/book.curl.php">' . Loc::getMessage('SMS4B_MAIN_CURL_NOT_FOUND') . '</a>' . Loc::getMessage('SMS4B_MAIN_CURL_LIB_NOT_INSTALL');
            die();
        }

        $ch = curl_init();

        if (curl_errno($ch) > 0) {
            $this->LastError = Loc::getMessage('SMS4B_MAIN_CURL_NOT_FOUND');
            return false;
        } else {
            if ($this->proxy_use === 'Y' && $this->proxy_serv_addr !== '' && $this->proxy_serv_port !== '') {
                curl_setopt($ch, CURLOPT_PROXY, $this->proxy_serv_addr . ':' . $this->proxy_serv_port);
                curl_setopt($ch, CURLOPT_HTTPPROXYTUNNEL, 1);
            }

            curl_setopt($ch, CURLOPT_URL, $this->serv_addr . $address);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $arrHeader);
            curl_setopt($ch, CURLOPT_HEADER, 1);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);
            curl_setopt($ch, CURLOPT_HTTP_VERSION, 1.1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);

            $response = curl_exec($ch);
            $lerror = curl_errno($ch);
            curl_close($ch);

            if ($lerror > 0 || empty($response)) {
                $this->LastError = Loc::getMessage('SMS4B_MAIN_CURL_ERROR') . '[' . $lerror . ']';
                return false;
            } else {
                return $response;
            }
        }
    }

    /**
     * Парсит ответ от сервиса и возвращает массив параметров
     *
     * @param $xml string - xml-ответ от сервиса
     * @param $params array - параметры для поиска в xml-ответе
     *
     * @return array - распарсенный ответ
     */
    protected function ParserTableResp($xml, array $params = array())
    {
        $arReports = array();

        if ($xml !== '' && count($params) > 1) {
            $pars_pref = substr(md5(time() + 'qwe123'), 0, 10); //kick inters

            $xml = str_replace(array("\r\n", "\n"), $pars_pref, $xml);
            $this->LastError = '';

            preg_match_all("/<Table.+?>(.+?)<\/Table>/i", $xml, $find);

            foreach ($find[1] as $key => $val) {
                $arReports[] = $this->ParserResp($val, $params);
            }
        }
        return $arReports;
    }

    /**
     * Парсит строку из xml-ответа и возвращает значение параметра
     *
     * @param $xml string - строка из xml-ответа от сервиса
     * @param $params array - параметры для поиска в xml-ответе
     *
     * @return mixed - распарсенный массив параметров/false
     */
    protected function ParserResp($xml, array $params = array())
    {
        $arResult = array();
        $this->LastError = '';
        if ($xml !== '' && count($params) > 1) {
            $pars_pref = substr(md5(time() + 'qwe123'), 0, 10); //kick inters

            $xml = str_replace(array("\r\n", "\n"), $pars_pref, $xml);

            foreach ($params as $param) {
                if (preg_match("/<$param>(.+?)<\/$param>/", $xml, $find)) {
                    $arResult[$param] = trim(str_replace($pars_pref, "\r\n", $find[1]));
                }
            }
            if (count($arResult) > 0) {
                return $arResult;
            } else {
                $this->LastError = Loc::getMessage('SMS4B_MAIN_SERVER_PARSE_ERROR');
                return false;
            }
        } else {
            $this->LastError = $xml === '' ? Loc::getMessage('SMS4B_MAIN_ERROR_NULL_XML') : Loc::getMessage('SMS4B_MAIN_ERROR_NULL_PARAMETERS');
            return false;
        }
    }

    /**
     * Делает запрос к сервису и возвращает распарсенный ответ
     *
     * @param $funcname string - имя функции
     * @param $param array - параметры для функции
     *
     * @return mixed - распарсенный ответ
     */
    public function GetSOAP($funcname = '', array $param = array())
    {
        $this->LastError = '';
        $response = $this->makeRequest($funcname, $param, $this->defClient, $this->defAddr);
        if ($response != -1) {
            switch ($funcname) {
                case 'StartSession':
                    if ($this->sid > 0) {
                        return true;
                    }

                    $this->sid = $this->StartSession($response);

                    if (!$this->sid < 0) {
                        $this->LastError = Loc::getMessage('SMS4B_MAIN_SESSION_UNKNOWN');
                        return false;
                    } elseif ($this->sid === 0) {
                        $this->LastError = Loc::getMessage('SMS4B_MAIN_SESSION_LOST');
                        return false;
                    } else {
                        $_SESSION['SMS_START_SESSION'] = $this->sid;
                        $this->LastError = '';
                        return true;
                    }
                    break;

                case 'LoadMessage':
                    $result = $this->LoadMessage($response);

                    if ($result['Result'] < 0) {
                        $this->LastError = Loc::getMessage('SMS4B_MAIN_ERROR_LOADMESSAGE');
                        return -1;
                    } elseif ($result['Result'] == 0) {
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

                case 'AccountParams':
                    if ($this->AccountParams($response)) {
                        return true;
                    } else {
                        return false;
                    }
                    break;

                case 'SaveGroup':
                    return $this->SaveGroup($response);
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

                case 'LoadSMS':
                    $LoadInResult = $this->LoadSMS($response);
                    if (count($LoadInResult) > 0) {
                        return $LoadInResult;
                    } else {
                        return false;
                    }

                    break;


                case 'GroupSMS':
                    $GroupSMSResult = $this->GroupSMS($response);
                    if (count($GroupSMSResult) > 0) {
                        return $GroupSMSResult;
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
     * Проверка логина\пароля пользователя
     *
     * @param $Login string - логин
     * @param $Password string - пароль
     *
     * @return bool - зарегистрирован\не зарегистрирован
     */
    public function IsRegUser($Login, $Password)
    {
        $props = array(
            'Login' => $Login,
            'Password' => $Password
        );
        return $this->GetSOAP('CheckUser', $props);
    }


    /**
     * Парсинг xml-ответа на запрос StartSession
     *
     * @param $xml string - xml-ответ
     *
     * @return string - SID
     */
    protected function StartSession($xml)
    {
        $xml = str_replace(array("\r\n", "\n"), '', $xml);
        preg_match("/<StartSessionResult>(\d+?)<\/StartSessionResult>/", $xml, $find);
        return (int)$find[1];
    }

    /**
     * Парсинг xml-ответа на запрос LoadSMS
     *
     * @param $xml string - xml-ответ
     *
     * @return array - распарсенный ответ сервиса
     */
    protected function LoadSMS($xml)
    {
        $xml = str_replace(array("\r\n", "\n"), '', $xml);
        $this->LastError = '';

        preg_match_all("/<SMSList>(.+?)<\/SMSList>/i", $xml, $find);

        $arReports = array();
        foreach ($find[1] as $key => $val) {
            $arReports[] = $this->ParserResp($val, array(
                    'G',
                    'D',
                    'B',
                    'E',
                    'A',
                    'P',
                    'M',
                    'T',
                    'S'
                )
            );
        }

        return $arReports;
    }

    /**
     * Парсинг xml-ответа на запрос CloseSession
     *
     * @param $xml string - xml-ответ
     *
     * @return array - SID
     */
    protected function CloseSession($xml)
    {
        $xml = str_replace(array("\r\n", "\n"), '', $xml);
        preg_match("/<CloseSessionResult>(\d+?)<\/CloseSessionResult>/", $xml, $find);

        return (int)$find[1];
    }

    /**
     * Парсинг xml-ответа на запрос AccountParams
     *
     * @param $xml string - xml-ответ
     *
     * @return array - SID
     */
    protected function AccountParams($xml)
    {
        $this->LastError = '';
        $this->arBalance = $this->ParserResp($xml, array('Result', 'Rest', 'Addresses'));

        if ($this->arBalance['Result'] < 1) {
            $this->LastError = Loc::getMessage('SMS4B_MAIN_ERROR_ACCOUNT_PARAMS');
            return false;
        } else {
            $this->arBalance['Addresses'] = explode("\r\n", $this->arBalance['Addresses']);
        }

        return true;
    }

    /**
     * Парсинг xml-ответа на запрос SaveGroup
     *
     * @param $xml string - xml-ответ
     *
     * @return array - массив параметров
     */
    protected function SaveGroup($xml)
    {
        $xml = str_replace(array("\r\n", "\n"), '', $xml);

        preg_match("/<Code>(.+?)<\/Code>/", $xml, $find);
        $resultArray['groupCode'] = (int)$find[1];

        preg_match("/<Result>(.+?)<\/Result>/", $xml, $result);
        $resultArray['result'] = (int)$result[1];

        return $resultArray;
    }


    /**
     * Парсинг xml-ответа на запрос GroupSMS
     *
     * @param $xml string - xml-ответ
     *
     * @return array - массив параметров
     */
    protected function GroupSMS($xml)
    {
        $xml = str_replace(array("\r\n", "\n"), '', $xml);

        preg_match("/<Group>(.+?)<\/Group>/", $xml, $find);
        $resultArray['groupCode'] = (int)$find[1];

        preg_match("/<Result>(.+?)<\/Result>/", $xml, $result);
        $resultArray['result'] = (int)$result[1];

        return $resultArray;
    }

    /**
     * Парсинг xml-ответа на запрос SaveMessage
     *
     * @param $xml string - xml-ответ
     *
     * @return int - SaveMessageResult
     */
    protected function SaveMessage($xml)
    {
        $xml = str_replace(array("\r\n", "\n"), '', $xml);
        preg_match("/<SaveMessageResult>(\d+?)<\/SaveMessageResult>/", $xml, $find);
        return (int)$find[1];
    }

    /**
     * Парсинг xml-ответа на запрос SaveMessages
     *
     * @param $xml string - xml-ответ
     *
     * @return array - массив с результатами отправки
     */
    protected function SaveMessages($xml)
    {
        $xml = str_replace(array("\r\n", "\n"), '', $xml);

        preg_match_all("/<SessionID>([0-9-]+?)<\/SessionID>/", $xml, $find);
        preg_match_all("/<Destination>(.+?)<\/Destination>/", $xml, $find_dest_num);
        $saveMessagesResult = $find[1];
        $dest_numbers = $find_dest_num[1];

        $result_array = array();
        $succes_send = 0;
        $not_send = 0;
        $array_for_counts = array();

        foreach ($saveMessagesResult as $arIndex) {
            if ((int)$arIndex > 0) {
                $ok = (int)$arIndex;
                $arrSaveres = array();
                $arrSaveres['SEND'] = 255 & $ok;
                $arrSaveres['OK'] = 255 & ($ok >> 8);

                $array_for_counts[] = $arrSaveres;

                $succes_send++;
            } else {
                $arrSaveres['SEND'] = 0;
                $arrSaveres['OK'] = 0;
                $array_for_counts[] = $arrSaveres;

                $not_send++;
            }
        }
        $result_array['WAS_SEND'] = $succes_send;
        $result_array['NOT_SEND'] = $not_send;
        $result_array['ARRAY_NUMBERS_ON_NOT_SEND'] = $dest_numbers;
        $result_array['FOR_ADDING_TO_BASE'] = $array_for_counts;

        return $result_array;
    }

    /**
     * Парсинг xml-ответа на запрос LoadMessage
     *
     * @param $xml string - xml-ответ
     *
     * @return array - массив с результатом загрузки сообщений
     */
    protected function LoadMessage($xml)
    {

        $param_array =
            array(
                'Result',
                'MessageID',
                'GUID',
                'TimeOff',
                'Moment',
                'SrcTON',
                'SrcNPI',
                'Source',
                'DstTON',
                'DstNPI',
                'Destination',
                'Coding',
                'Body',
                'Total',
                'Part',
                'SMSCID',
                'Receiption'
            ,
                'NeedAnswer'
            );

        $xml = str_replace(array("\r\n", "\n"), '', $xml);
        return $this->ParserResp($xml, $param_array);
    }

    /**
     * Парсинг xml-ответа на запрос LoadResponse
     *
     * @param $xml string - xml-ответ
     *
     * @return int - код результата
     */
    protected function LoadResponse($xml)
    {
        $xml = str_replace(array("\r\n", "\n"), '', $xml);
        preg_match("/<LoadResponseResult>(\d+?)<\/LoadResponseResult>/", $xml, $find);
        return (int)$find[1];
    }

    /**
     * Возвращает логин пользователя
     *
     * @return string - логин
     */
    public function getLogin()
    {
        $login = explode(' ', $this->login);
        return $login['2'];
    }

    /**
     * Возвращает пароль пользователя
     *
     * @return string - логин
     */
    public function getPassword()
    {
        return $this->password;
    }

    /**
     * Возвращает часовой пояс из настроек модуля
     *
     * @return int - часовой пояс
     */
    public function getUserGMT()
    {
        return $this->gmt;
    }

    /**
     * Возвращает SID
     *
     * @return int - SID
     */
    public function GetSID()
    {
        return $this->sid;
    }

    /**
     * Генератор GUID
     *
     * @return string - GUID
     */
    public function CreateGuid()
    {
        if (function_exists('com_create_guid')) {
            return $this->eraseBrackets(com_create_guid());
        } else {
            mt_srand((double)microtime() * 10000);
            $charid = strtoupper(md5(uniqid(mt_rand(), true)));
            $hyphen = chr(45);// "-"
            $uuid = substr($charid, 0, 8) . $hyphen
                . substr($charid, 8, 4) . $hyphen
                . substr($charid, 12, 4) . $hyphen
                . substr($charid, 16, 4) . $hyphen
                . substr($charid, 20, 12);
            return $uuid;
        }
    }

    /**
     * Чистит строку от символов '{' и '}'
     *
     * @param $str - входная строка
     *
     * @return string - очищенная строка от символов '{' и '}'
     */
    protected function eraseBrackets($str)
    {
        return str_replace(array('{', '}'), '', $str);
    }

    /**
     * Преобразует бинарные данные в шестнадцатеричное представление
     *
     * @param $str - входная строка
     *
     * @return string - преобразованная строка
     */
    public function bin_to_hex($str)
    {
        return bin2hex($str);
    }

    /**
     * Преобразует шестнадцатеричные данные в бинарные
     * Используется, т.к. hex2bin() требует PHP >= 5.4.0
     *
     * @param $str - входная строка
     *
     * @return string - преобразованная строка
     */
    public static function HexToBin($str)
    {
        $sbin = '';
        $len = strlen($str);
        for ($i = 0; $i < $len; $i += 2) {
            $sbin .= pack('H*', substr($str, $i, 2));
        }

        return $sbin;
    }

    /**
     * Возвращает тип кодирования строки: UTF-16 = return 1, DefaultAlphabet = return 0
     *
     * @param $message string - входная строка
     *
     * @return int - тип кодирования строки
     */
    public function get_type_of_encoding($message)
    {
        if ($message === '') {
            $this->LastError = Loc::getMessage('SMS4B_MAIN_NO_SYMBOLS');
            return false;
        }
        //Регулярка найдёт любой символ, кроме разрешённых для отправки в GSM-кодировке.
        $regExpExcludeGsm = '/[^\n\r !"#$%&\'()*+,\\-.\\/0-9:;<=>?@A-Za-z]/';

        if(!preg_match($regExpExcludeGsm, $message))
        {
            return self::MESSAGE_ENCODING_GSM;
        }
        else
        {
                return self::MESSAGE_ENCODING_UNICODE;
            }
        }

    /**
     * Декодирование сообщения
     *
     * @param $message string - текст сообщения
     * @param $typeEnc - тип кодировки
     * 0-DefaultAlphabet
     * 1-UTF16
     *
     * @return string - декодированный текст
     */
    public function decode($message, $typeEnc)
    {
        if($typeEnc === self::MESSAGE_ENCODING_GSM)
        {
            return self::DecodeMessageGsm($message);
        }
        else
        {
            return self::DecodeMessageUnicode($message);
        }
    }

    /**
     * Декодировать сообщение в кодировке Unicode (UCS-2).
     * @param string $message - текст закодированного сообщения
     * @return string - декодированное сообщение
     * @author ukhvan
     */
    public static function DecodeMessageUnicode($message)
    {
        $msgDecodeToUtf8 = mb_convert_encoding(self::HexToBin($message), 'UTF-8', 'UCS-2');

        //Если нет спец-символов - возвращаем результат
        if (!preg_match("/[\200-\237]/", $msgDecodeToUtf8)
            && !preg_match("/[\241-\377]/", $msgDecodeToUtf8)
        ) {
            return $msgDecodeToUtf8;
        }

        //Декодируем 3-байтовые символы юникода
        $msgDecodeToUtf8 = preg_replace("/([\340-\357])([\200-\277])([\200-\277])/e",
            "'&#'.((ord('\\1')-224)*4096 + (ord('\\2')-128)*64 + (ord('\\3')-128)).';'",
            $msgDecodeToUtf8
        );

        //Декодируем 2-байтовые символы юникода
        $msgDecodeToUtf8 = preg_replace("/([\300-\337])([\200-\277])/e",
            "'&#'.((ord('\\1')-192)*64+(ord('\\2')-128)).';'",
            $msgDecodeToUtf8
        );

        return $msgDecodeToUtf8;
    }

    /**
     * Декодировать сообщение в кодировке GSM 03.38.
     * @param string $message - текст закодированного сообщения
     * @return string - декодированное сообщение
     * @author ukhvan
     */
    public static function DecodeMessageGsm($message)
    {
        return strtr(self::HexToBin($message), array(
            chr(0x02) => '$',
            //Обратную замену тажке необходимо выполнить, чтобы, если в исходном сообщении были $ и/или @,
            //они не остались там без изменения.
            '$' => chr(0x02),
            chr(0x00) => '@',
            '@' => chr(0x00)
        ));
    }

    /**
     * @deprecated use enCodeMessage($symbol)
     * Декодировка символа
     * 0-DefaultAlphabet
     * 1-UTF16
     * @deprecated
     * @param $symbol string - символ
     * @param $type_of_encoding string - тип кодировки
     *
     * @return string - декодированный символ
     */
    public function enCoding($symbol, $type_of_encoding)
    {
        return $this->enCodeMessage($symbol);
    }

    /**
     * Автоматически определить необходимую кодировку и закодировать в ней сообщение.
     *
     * @param $message string - текст сообщения
     *
     * @return mixed - декодированное сообщение\false
     */
    public function enCodeMessage($message)
    {
        if (empty($message)) {
            $this->LastError = Loc::getMessage('SMS4B_MAIN_NO_SYMBOLS');
            return false;
        }

        $encoding = $this->get_type_of_encoding($message);

        if ($encoding === self::MESSAGE_ENCODING_GSM) {
            $message = self::EncodeMessageGsm($message);
        } else {
            $message = self::EncodeMessageUnicode($message);
        }

        return $message;
    }

    /**
    * Закодировать сообщение в кодировке Unicode (UCS-2).
     *
    * @param string $message - текст сообщения
     *
    * @return string - закодированное сообщение
    */
    public static function EncodeMessageUnicode($message)
    {
        return bin2hex(mb_convert_encoding($message, 'UCS-2', 'UTF-8'));
    }

    /**
     * Закодировать сообщение в кодировке GSM 03.38 (только разрешённые символы, см. MESSAGE_ENCODING_GSM).
     *
     * @param string $message - текст сообщения
     *
     * @return string - закодированное сообщение
     */
    public static function EncodeMessageGsm($message)
    {
        return bin2hex(strtr($message, array('$' => chr(0x02), '@' => chr(0x00))));
    }

    /**
     * Преобразует данные в шестнадцатеричном представлении в юникод
     *
     * @param $str string - входная строка
     *
     * @return string - преобразованная строка
     */
    public function hex2unicode($str)
    {
        $returned_string = '';
        if (strlen($str) % 4 == 0) {
            for ($i = 0, $iMax = strlen($str); $i < $iMax; $i += 4) {
                $code = substr($str, $i, 4);
                $code = base_convert($code, 16, 10);
                $returned_string .= '&#' . $code . ';';
            }
        }
        return $returned_string;
    }

    /**
     * Записывает в $this->LastError описание ошибки по ее коду
     *
     * @param $rezult string - код ошибки
     */
    protected function AnalyzeResultSaveMessage($rezult)
    {
        switch ($rezult) {
            case 0:
                $this->LastError = Loc::getMessage('SMS4B_MAIN_SAVE_MESSAGE_RESPONSE_0');
                return;
            case -1:
                $this->LastError = Loc::getMessage('SMS4B_MAIN_SAVE_MESSAGE_RESPONSE_1');
                return;
            case -2:
                $this->LastError = Loc::getMessage('SMS4B_MAIN_SAVE_MESSAGE_RESPONSE_2');
                return;
            case -3:
            case -4:
            case -5:
            case -6:
            case -7:
            case -8:
            case -9:
            case -10:
            case -11:
            case -12:
            case -13:
            case -14:
            case -15:
            case -16:
            case -17:
            case -18:
            case -19:
                $this->LastError = Loc::getMessage('SMS4B_MAIN_SAVE_MESSAGE_RESPONSE_19');
                return;
            case -20:
                $this->LastError = Loc::getMessage('SMS4B_MAIN_SAVE_MESSAGE_RESPONSE_20');
                return;
            case -21:
                $this->LastError = Loc::getMessage('SMS4B_MAIN_SAVE_MESSAGE_RESPONSE_21');
                return;
            case -22:
                $this->LastError = Loc::getMessage('SMS4B_MAIN_SAVE_MESSAGE_RESPONSE_22');
                return;
            case -30:
                $this->LastError = Loc::getMessage('SMS4B_MAIN_SAVE_MESSAGE_RESPONSE_30');
                return;
            case -31:
                $this->LastError = Loc::getMessage('SMS4B_MAIN_SAVE_MESSAGE_RESPONSE_31');
                return;
            case -50:
                $this->LastError = Loc::getMessage('SMS4B_MAIN_SAVE_MESSAGE_RESPONSE_50');
                return;
            case -52:
                $this->LastError = Loc::getMessage('SMS4B_MAIN_SAVE_MESSAGE_RESPONSE_52');
                return;
            case -68:
                $this->LastError = Loc::getMessage('SMS4B_MAIN_SAVE_MESSAGE_RESPONSE_68');
                return;

            default: {
                $this->LastError = Loc::getMessage('SMS4B_MAIN_SAVE_MESSAGE_RESPONSE_UNDEFINED');
            }
        }
    }

    /**
     * Возвращает TON по номеру
     * International = 1
     * National = 2
     * Network Specific = 3
     * Alphanumeric = 5
     *
     * @param $addr string - номер
     *
     * @return int - Тип TON
     */
    public function get_ton($addr)
    {
        $addr = htmlspecialchars($addr);
        if (preg_match('/^(\d{1,10})$/', $addr)) {
            return 3;
        } elseif (preg_match('/^8(\d{10})$/', $addr)) {
            return 2;
        } elseif (preg_match('/^(\d{11,15})$/', $addr)) {
            return 1;
        } else {
            return 5;
        }
    }

    /**
     * Возвращает NPI по номеру
     * Unknown = 0
     * ISDN/telephone numbering plan (E163/E164) = 1
     * Private numbering plan = 9
     *
     * @param $addr string - номер
     *
     * @return int - Тип NPI
     */
    public function get_npi($addr)
    {
        $addr = htmlspecialchars($addr);
        if (preg_match('/^(\d{1,10})$/', $addr)) {
            return 9;
        } elseif (preg_match('/^8(\d{10})$/', $addr)) {
            return 1;
        } elseif (preg_match('/^(\d{11,15})$/', $addr)) {
            return 1;
        } else {
            return 0;
        }
    }

    /**
     * Метод для проверки мобильного номера телефона
     *
     * @param $number - номер телефона
     *
     * @return string|false - номер или false
     */
    public function is_phone($number)
    {
        $number = preg_replace('/[\D]/', '', $number);

        if (preg_match(self::PHONE_PATTERN, $number)) {
            if (strlen($number) == 10) {
                $number = '7' . $number;
            } else {
                $number[0] = '7';
            }
            return $number;
        } else {
            return false;
        }
    }

    /**
     * Форматирует дату
     *
     * @param $date string - дата
     *
     * @return string - отформатированная дата
     */
    public function GetFormatDate($date)
    {
        $date = htmlspecialchars($date);
        if (preg_match("/^\d{2}\.\d{2}\.\d{4}$/", $date)) {
            return ConvertDateTime($date, 'YYYYMMDD 23:59:59', 'ru');
        }
        if (preg_match("/^\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}:\d{2}$/", $date)) {
            return ConvertDateTime($date, 'YYYYMMDD HH:MI:SS', 'ru');
        } else {
            return '';
        }
    }

    /**
     * Транслитерация в формате МВД
     *
     * @param $cyr_str string - строка для транслитерации
     *
     * @return string - транслитерированная строка
     */
    public function Translit($cyr_str)
    {
        $tr = array(
            Loc::getMessage('SMS4B_MAIN_a') => 'a',
            Loc::getMessage('SMS4B_MAIN_b') => 'b',
            Loc::getMessage('SMS4B_MAIN_v') => 'v',
            Loc::getMessage('SMS4B_MAIN_g') => 'g',
            Loc::getMessage('SMS4B_MAIN_d') => 'd',
            Loc::getMessage('SMS4B_MAIN_e') => 'e',
            Loc::getMessage('SMS4B_MAIN_yo') => 'yo',
            Loc::getMessage('SMS4B_MAIN_zh') => 'zh',
            Loc::getMessage('SMS4B_MAIN_z') => 'z',
            Loc::getMessage('SMS4B_MAIN_i') => 'i',
            Loc::getMessage('SMS4B_MAIN_j') => 'j',
            Loc::getMessage('SMS4B_MAIN_k') => 'k',
            Loc::getMessage('SMS4B_MAIN_l') => 'l',
            Loc::getMessage('SMS4B_MAIN_m') => 'm',
            Loc::getMessage('SMS4B_MAIN_n') => 'n',
            Loc::getMessage('SMS4B_MAIN_o') => 'o',
            Loc::getMessage('SMS4B_MAIN_p') => 'p',
            Loc::getMessage('SMS4B_MAIN_r') => 'r',
            Loc::getMessage('SMS4B_MAIN_s') => 's',
            Loc::getMessage('SMS4B_MAIN_t') => 't',
            Loc::getMessage('SMS4B_MAIN_u') => 'u',
            Loc::getMessage('SMS4B_MAIN_f') => 'f',
            Loc::getMessage('SMS4B_MAIN_h') => 'h',
            Loc::getMessage('SMS4B_MAIN_c') => 'c',
            Loc::getMessage('SMS4B_MAIN_ch') => 'ch',
            Loc::getMessage('SMS4B_MAIN_sh') => 'sh',
            Loc::getMessage('SMS4B_MAIN_shh') => 'shh',
            Loc::getMessage("\"") => "\"",
            Loc::getMessage('SMS4B_MAIN_y') => 'y',
            Loc::getMessage("'") => "'",
            Loc::getMessage('SMS4B_MAIN_ye') => 'ye',
            Loc::getMessage('SMS4B_MAIN_yu') => 'yu',
            Loc::getMessage('SMS4B_MAIN_ya') => 'ya',

            Loc::getMessage('SMS4B_MAIN_A') => 'A',
            Loc::getMessage('SMS4B_MAIN_B') => 'B',
            Loc::getMessage('SMS4B_MAIN_V') => 'V',
            Loc::getMessage('SMS4B_MAIN_G') => 'G',
            Loc::getMessage('SMS4B_MAIN_D') => 'D',
            Loc::getMessage('SMS4B_MAIN_E') => 'E',
            Loc::getMessage('SMS4B_MAIN_YO') => 'YO',
            Loc::getMessage('SMS4B_MAIN_ZH') => 'ZH',
            Loc::getMessage('SMS4B_MAIN_Z') => 'Z',
            Loc::getMessage('SMS4B_MAIN_I') => 'I',
            Loc::getMessage('SMS4B_MAIN_J') => 'J',
            Loc::getMessage('SMS4B_MAIN_K') => 'K',
            Loc::getMessage('SMS4B_MAIN_L') => 'L',
            Loc::getMessage('SMS4B_MAIN_M') => 'M',
            Loc::getMessage('SMS4B_MAIN_N') => 'N',
            Loc::getMessage('SMS4B_MAIN_O') => 'O',
            Loc::getMessage('SMS4B_MAIN_P') => 'P',
            Loc::getMessage('SMS4B_MAIN_R') => 'R',
            Loc::getMessage('SMS4B_MAIN_S') => 'S',
            Loc::getMessage('SMS4B_MAIN_T') => 'T',
            Loc::getMessage('SMS4B_MAIN_U') => 'U',
            Loc::getMessage('SMS4B_MAIN_F') => 'F',
            Loc::getMessage('SMS4B_MAIN_H') => 'H',
            Loc::getMessage('SMS4B_MAIN_C') => 'C',
            Loc::getMessage('SMS4B_MAIN_CH') => 'CH',
            Loc::getMessage('SMS4B_MAIN_SH') => 'SH',
            Loc::getMessage('SMS4B_MAIN_SHH') => 'SHH',
            Loc::getMessage("\"\"") => "\"",
            Loc::getMessage('SMS4B_MAIN_Y') => 'Y',
            Loc::getMessage("''") => "'",
            Loc::getMessage('SMS4B_MAIN_YE') => 'YE',
            Loc::getMessage('SMS4B_MAIN_YU') => 'YU',
            Loc::getMessage('SMS4B_MAIN_YA') => 'YA',

            Loc::getMessage('SMS4B_MAIN_<') => '<',
            Loc::getMessage('SMS4B_MAIN_>') => '>',
            Loc::getMessage('SMS4B_MAIN_-') => '-'
        );

        $str = strtr($cyr_str, $tr);

        $str = str_replace(array('^', '`'), "'", $str);
        $str = str_replace(array('?'), "\"", $str);
        $str = str_replace(array('{', '['), '(', $str);
        $str = str_replace(array('}', ']'), ')', $str);
        $str = str_replace(array('\\'), '/', $str);
        $str = str_replace(array('_', '~'), '-', $str);
        $str = str_replace(array('|'), 'i', $str);
        $str = str_replace(array('?'), 'N', $str);

        return $str;
    }


    /**
     * Отправка одиночного сообщения
     *
     * @param $message string - текст сообщения
     * @param $to mixed - строка с номером или false, если номер не валиден
     * @param $sender string - отправитель
     *
     * @return bool - результат выполнения
     */
    public function SendSMS($message, $to, $sender = '')
    {
        if ($sender == '') {
            $sender = $this->DefSender;
        }
        $to = $this->is_phone($to);
        if (strlen($sender) > 0 && $this->is_phone($to) && strlen($message) > 0) {
            $ston = $this->get_ton($sender);
            $snpi = $this->get_npi($sender);

            $dton = $this->get_ton($to);
            $dnpi = $this->get_npi($to);

            $body = $this->enCodeMessage($message);
            $encoded = $this->get_type_of_encoding($message);
            $date_actual = date('Ymd H:i:s', time() + 86400 * 7);
            $outsms_guid = $this->CreateGuid();


            $params_sms = array(
                'SessionID' => $this->GetSID(),
                'guid' => $outsms_guid,
                'Destination' => $to,
                'Source' => $sender,
                'Body' => $body,
                'Encoded' => $encoded,
                'dton' => $dton,
                'dnpi' => $dnpi,
                'ston' => $ston,
                'snpi' => $snpi,
                'TimeOff' => $date_actual,
                'Priority' => 0,
                'NoRequest' => 0
            );

            $resSendMess = $this->GetSOAP('SaveMessage', $params_sms);

            if ($resSendMess) {
                return true;
            } else {
                return false;
            }
        }
        return false;
    }

    /**
     * Отправка сообщения на несколько номеров
     *
     * @param $message string - текст сообщения
     * @param $to mixed - строка или массив с номерами
     * @param $sender string - отправитель
     * @param $startUp_p string - дата старта рассылки
     * @param $dateActual_p string - дата актуальности рассылки
     * @param $period_p string - запрет отправки в определенный период веремени в буквенном представлении
     *
     * @return array - результат выполнения
     */
    public function SendSmsPack($message, $to, $sender = '', $startUp_p = '', $dateActual_p = '', $period_p = '')
    {
        $session = $this->GetSID();
        $code = -1;
        $encoded = $this->get_type_of_encoding($message);
        $body = $this->enCodeMessage($message);
        $dateActual = $dateActual_p;
        $startUp = $startUp_p;
        $period = $period_p;
        $destination = $this->parse_numbers($to);

        if ($sender == '') {
            $sender = $this->DefSender;
        }

        $sms_package = array();

        foreach ($destination as $arInd) {
            $outsms_guid = $this->CreateGuid();

            $one_sms = array(
                'G' => $outsms_guid,
                'D' => $arInd,
                'B' => $body,
                'E' => $encoded
            );

            $sms_package[] = $one_sms;
        }

        $results_of_package_send = array();
        $results_of_package_send['SEND'] = 0;
        $results_of_package_send['NOT_SEND'] = 0;

        if (count($sms_package) < $this->maxPackage) {
            $temp = $this->GetSOAP('GroupSMS', array(
                    'SessionId' => $session,
                    'Group' => $code,
                    'Source' => $sender,
                    'Encoding' => $encoded,
                    'Body' => $body,
                    'Off' => $dateActual,
                    'Start' => $startUp,
                    'Period' => $period,
                    'List' => $sms_package
                )
            );

            if ((int)$temp['result'] > 0) {
                $results_of_package_send['SEND'] += $temp['result'];
            } else {
                $results_of_package_send['NOT_SEND'] += count($sms_package);
            }
        } else {
            $big_array = array_chunk($sms_package, $this->maxPackage, true);

            foreach ($big_array as $arIndex) {
                $temp = $this->GetSOAP('GroupSMS', array(
                        'SessionId' => $session,
                        'Group' => $code,
                        'Source' => $sender,
                        'Encoding' => $encoded,
                        'Body' => $body,
                        'Off' => $dateActual,
                        'Start' => $startUp,
                        'Period' => $period,
                        'List' => $arIndex
                    )
                );

                if ((int)$temp['result'] > 0) {
                    $results_of_package_send['SEND'] += $temp['result'];
                } else {
                    $results_of_package_send['NOT_SEND'] += count($sms_package);
                }
            }
        }
        return $results_of_package_send;
    }

    /**
     * Возвращает символьное имя отправителя
     *
     * @return array - символьные имена отправителя
     */
    public function GetSender()
    {
        return $this->arBalance['Addresses'];
    }

    /**
     * Обовление SID
     */
    protected function UpdateSID()
    {
        if (empty($_SESSION['SMS_START_SESSION'])) {
            $this->MakeSID();
        } else {
            if (!$this->GetSOAP('AccountParams', array('SessionID' => $_SESSION['SMS_START_SESSION']))) {
                $this->MakeSID();
            } else {
                $this->sid = $_SESSION['SMS_START_SESSION'];
            }
        }
    }

    /**
     * Получает SID, записывает параметры аккаунта в свойства класса
     *
     * @return bool - результат выполнения
     */
    protected function MakeSID()
    {
        $arParam = array(
            'Login' => $this->login,
            'Password' => $this->password,
            'Gmt' => $this->gmt
        );

        if ($this->GetSOAP('StartSession', $arParam)) {
            $this->GetSOAP('AccountParams', array('SessionID' => $this->sid));
            return true;
        } else {
            return false;
        }
    }

    /**
     * Парсит номера
     *
     * @param $destination_numbers mixed - строка или массив с номерами
     *
     * @return array - распарсенный массив номеров
     */
    public function parse_numbers($destination_numbers)
    {
        $numbers = array();
        if (!is_array($destination_numbers)) {
            $destination_numbers = str_replace(array(',', "\n"), ';', trim($destination_numbers));
            $sort_numbers = explode(';', $destination_numbers);
        } else {
            $sort_numbers = $destination_numbers;
        }

        foreach ($sort_numbers as $arInd) {
            $arInd = trim($arInd);

            $symbol = false;
            $spec_sym = array('+', '(', ')', ' ', '-', '_');
            for ($i = 0, $iMax = strlen($arInd); $i < $iMax; $i++) {
                if (!is_numeric($arInd[$i]) && !in_array($arInd[$i], $spec_sym, false)) {
                    $symbol = true;
                }
            }

            if ($symbol) {
                $numbers[] = $arInd;
            } else {
                $arInd = str_replace($spec_sym, '', $arInd);

                $strlenArInd = strlen($arInd);
                if ($strlenArInd < 4 || $strlenArInd > 15) {
                    continue;
                } else {
                    if ($strlenArInd == 10 && $arInd[0] == '9') {
                        $arInd = '7' . $arInd;
                    }
                    if ($strlenArInd == 11 && $arInd[0] == '8') {
                        $arInd[0] = '7';
                    }
                    $numbers[] = $arInd;
                }
            }
        }

        return array_unique($numbers);
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
        if (preg_match("/^(\d{2})\-(\d{2})\-(\d{4})$/", $date, $matches)) {
            if (checkdate($matches[2], $matches[1], $matches[3])) {
                return $matches[3] . $matches[2] . $matches[1] . ' ' . $forShortTime;
            } else {
                return -1;
            }
        }

        if (preg_match("/^(\d{2})-(\d{2})-(\d{4}) \d{2}:\d{2}:\d{2}$/", $date, $matches)) {
            if (checkdate($matches[2], $matches[1], $matches[3])) {
                $daysHours = explode(' ', $date);
                return $matches[3] . $matches[2] . $matches[1] . ' ' . $daysHours[1];
            } else {
                return -1;
            }
        }

        return -1;
    }

    /**
     * Возвращает TimeStamp по дате
     *
     * @param $date string - дата
     *
     * @return int - TimeStamp или -1
     */
    public function GetTimeStamp($date)
    {
        if (preg_match("/^(\d{2})\-(\d{2})\-(\d{4})$/", $date, $matches)) {
            return mktime(0, 0, 0, $matches[2], $matches[1], $matches[3]);
        }

        if (preg_match("/^(\d{2})-(\d{2})-(\d{4}) (\d{2}):(\d{2}):(\d{2})$/", $date, $matches)) {
            return mktime($matches[4], $matches[5], $matches[6], $matches[2], $matches[1], $matches[3]);
        }

        return -1;
    }
}