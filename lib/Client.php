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
	 * @var string ��������� ������
	 */
	private static $instance;
	/**
	 * @var string ����� �������� �������
	 */
	private $servMain = 'https://sms4b.ru/webservices/sms.asmx?WSDL';
	/**
	 * @var string ����� ���������� �������
	 */
	private $servReserv = 'https://s.sms4b.ru/webservices/sms.asmx?WSDL';
	/**
	 * @var int ������� SOAP-�������
	 */
	private $timeoutSOAP = 5;
	/**
	 * @var object ������ �������
	 */
	private $client;
	/**
	 * @var array ������ �������, ��������� � WSDL
	 */
	private $functionsList;
	/**
	 * @var string ����� �������, � ������� ���� ������
	 */
	private $currentServiceAddress = '';
	/**
	 * @var string ��� �������, � ������� ���� ������
	 */
	private $currentServiceType = '';

	/**
	 * ������� Singleton
	 *
	 * @throws Sms4bException - ������
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
	 * �����������
	 * @param bool $reserve - ���� ��� ����������� � ���������� �������
	 * @param int $soapTimeout - ������� SOAP
	 * @throws Sms4bException - ������
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

		//��������� ����������� ������������ ���������� SOAP-�������
		if(empty($soapTimeout))
		{
			$soapTimeout = $this->timeoutSOAP;
		}

		$serviceOk = true;
		try
		{
			//������� SOAP ������
			$this->client = new \SoapClient($this->currentServiceAddress, array('connection_timeout' => (int)$soapTimeout, 'trace' => true));

			//�������� ������ �������
			$tmpFunctionsList = $this->client->__getFunctions();
		}
		catch (Sms4bException $e)
		{
			$serviceOk = false;
		}
		if(!$serviceOk && !$reserve)//���� ���� �������� - ������� ����������� �� ��������� ������
		{
			$this->currentServiceAddress = $this->servReserv;
			$this->currentServiceType = 'reserve';
			try
			{
				//������� SOAP ������
				$this->client = new \SoapClient($this->currentServiceAddress, array('connection_timeout' => (int)$soapTimeout, 'trace' => true));

				//�������� ������ �������
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
	* �������-����������� ��������� � ������� ������. �������������� ��������� � SOAP-�������. ����� ������ ����� �� ����.
	 *
	* @param string $methodName - �������� ������
	* @param array $methodArgs - ��������� ������
	* @throws Sms4bException - ������
	*
	* @return array - ������������ ����� �� �������
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
	 * ���������� ����� �������
	 *
	 * @return string ����� �������
     */
	public function GetServiceAddress()
	{
		return $this->currentServiceAddress;
	}

	/**
	* ������� ������ SOAP-�������. ����� ������, ��� ��� ����� ����� � �������������� ����� ������ ������ ��� ����� xml-������ � �������
	*
	* @param mixed $response - xml ����� �������
	*
	* @return object - ������������ ��������� ������
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
	 * ����������� ��������� ������ � ������������� ������
	 *
	 * @param object $response - ������
	 *
	 * @return array - ������, ��������������� � ������
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
	 * ���������� �������� ������ �� �� ����
	 *
	 * @param int $code - ��� ������
	 *
	 * @return mixed - �������� ������ ��� false, ���� �� ��� ������� ���
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