<?
namespace Rarus\Sms4b;

use Bitrix\Main\Localization\Loc;
use Rarus\Sms4b;

Loc::loadLanguageFile(__FILE__);
/**
* @author azarev
* @version 1.0
*/
class Sms4bClient
{
	/**
	 * @var string Экземпляр класса
	 */
	private static $instance;
	/**
	 * @var string Адрес главного сервиса
	 */
	private $servMain = 'https://sms4b.ru/webservices/sms.asmx?WSDL';
	/**
	 * @var string Адрес резервного сервиса
	 */
	private $servReserv = 'https://s.sms4b.ru/webservices/sms.asmx?WSDL';
	/**
	 * @var int Таймаут SOAP-запроса
	 */
	private $timeoutSOAP = 5;
	/**
	 * @var object Объект клиента
	 */
	private $client;
	/**
	 * @var array Массив функций, описанных в WSDL
	 */
	private $functionsList;
	/**
	 * @var string Адрес сервиса, с которым идет работа
	 */
	private $currentServiceAddress = '';
	/**
	 * @var string Тип сервиса, с которым идет работа
	 */
	private $currentServiceType = '';

	/**
	 * Паттерн Singleton
	 *
	 * @throws Sms4bException - ошибки
	 * @return object Sms4bClient
     */
	public static function getInstance()
	{
		if(self::$instance === null)
		{
			self::$instance = new Sms4bClient(false);
		}
		return self::$instance;
	}

	/**
	 * Конструктор
	 * @param bool $reserve - флаг для подключения к резервному сервису
	 * @param int $soapTimeout - таймаут SOAP
	 * @throws Sms4bException - ошибки
	 */
	protected function __construct($reserve = false, $soapTimeout = 0)
	{
		if(!class_exists('SoapClient'))
		{
			throw new Sms4bException(Loc::getMessage('SMS4B_MAIN_SOAP_NOT_EXIST'));
		}
		$this->currentServiceAddress = $reserve ? $this->servReserv : $this->servMain;
		$this->currentServiceType = $reserve ? 'reserve' : 'main';
		$tmpFunctionsList = array();

		//установка масимальной длительности выполнения SOAP-запроса
		if(empty($soapTimeout))
		{
			$soapTimeout = $this->timeoutSOAP;
		}

		$serviceOk = true;
		try
		{
			//создаем SOAP клиент
			$this->client = new \SoapClient($this->currentServiceAddress, array('connection_timeout' => (int)$soapTimeout, 'trace' => true));

			//получаем список функций
			$tmpFunctionsList = $this->client->__getFunctions();
		}
		catch (Sms4bException $e)
		{
			$serviceOk = false;
		}
		if(!$serviceOk && !$reserve)//если есть проблемы - пробуем переключить на резервный сервер
		{
			$this->currentServiceAddress = $this->servReserv;
			$this->currentServiceType = 'reserve';
			try
			{
				//создаем SOAP клиент
				$this->client = new \SoapClient($this->currentServiceAddress, array('connection_timeout' => (int)$soapTimeout, 'trace' => true));

				//получаем список функций
				$tmpFunctionsList = $this->client->__getFunctions();
			}
			catch (Sms4bException $e)
			{
				throw new Sms4bException(Loc::getMessage('SMS4B_MAIN_ERROR_CONNECT'));
			}
		}

		foreach($tmpFunctionsList as $functionString)
		{
			$tmp = explode(' ', $functionString);
			$tmp = explode('(', $tmp[1]);
			$this->functionsList[$tmp[0]] = $tmp[0];
		}
	}

	/**
	* Функция-перехватчик обращений к методам класса. Перенаправляет обращения к SOAP-клиенту. Также парсит ответ от него.
	 *
	* @param string $methodName - Название метода
	* @param array $methodArgs - Аргументы метода
	* @throws Sms4bException - ошибки
	*
	* @return array - распарсенный ответ от сервиса
	*/
	public function __call($methodName, $methodArgs)
	{
		if(!isset($this->functionsList[$methodName]))
		{
			throw new Sms4bException(Loc::getMessage('SMS4B_MAIN_ERROR_METHOD_NAME'));
		}

		$resultName = $methodName . 'Result';

		try
		{
			$response = $this->client->$methodName($methodArgs[0]);
		}
		catch (Sms4bException $e)
		{
			throw new Sms4bException(Loc::getMessage('SMS4B_MAIN_ERROR_METHOD_EXEC'));
		}

		if(!property_exists($response, $resultName))
		{
			throw new Sms4bException(Loc::getMessage('SMS4B_MAIN_ERROR_FORMAT_RESPONSE_FAIL'));
		}

		return $this->ObjToArray($this->ParseResponse($response->$resultName));
	}

	/**
	 * Возвращает адрес сервиса
	 *
	 * @return string адрес сервиса
     */
	public function GetServiceAddress()
	{
		return $this->currentServiceAddress;
	}

	/**
	* Парсинг ответа SOAP-клиента. Нужен потому, что для части полей с неопределенным типом клиент отдает нам сырые xml-строки с данными
	*
	* @param mixed $response - xml ответ сервиса
	*
	* @return object - распарсенное объектное дерево
	*/
	private function ParseResponse($response)
	{
		if(is_object($response) && strpos($response->any, 'xmlns'))
		{
			$xmlTree = new \SimpleXMLElement($response->any);
			return $xmlTree->NewDataSet;
		}
		return $response;
	}

	/**
	 * Преобразует объектное дерево в ассоциативный массив
	 *
	 * @param object $response - объект
	 *
	 * @return array - объект, преобразованный в массив
	 */
	private function ObjToArray($response)
	{
		$arRes = array();
		foreach((object)$response as $fieldName => $fieldValue)
		{
			if(is_object($fieldValue))
			{
				$arRes[$fieldName] = $this->ObjToArray(get_object_vars($fieldValue));
			}
			else
			{
				$arRes[$fieldName] = is_array($fieldValue) ? $this->ObjToArray($fieldValue) : $fieldValue;
			}
		}
		return $arRes;
	}

	/**
	 * Возвращает описание ошибки по ее коду
	 *
	 * @param int $code - код ошибки
	 *
	 * @return mixed - описание ошибки или false, если не был передан код
	 */
	public static function GetCodeDescription($code)
	{
		if(empty($code))
		{
			return false;
		}
		if($code > 0)
		{
			return Loc::getMessage('SMS4B_MAIN_SEND_SUCCESS');
		}
		$allCodes = array(
			-65 => Loc::getMessage('SMS4B_MAIN_ERROR_65'),
			-29 => Loc::getMessage('SMS4B_MAIN_ERROR_29'),
			-68 => Loc::getMessage('SMS4B_MAIN_ERROR_68'),
			-52 => Loc::getMessage('SMS4B_MAIN_ERROR_52'),
			-10 => Loc::getMessage('SMS4B_MAIN_ERROR_10'),
			-31 => Loc::getMessage('SMS4B_MAIN_ERROR_31')
		);

		return $allCodes[$code] ?: Loc::getMessage('SMS4B_MAIN_ERROR_DEFAULT') . "[$code]";
	}

}
?>