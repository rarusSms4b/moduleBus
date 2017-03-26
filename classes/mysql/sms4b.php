<?
require_once($_SERVER["DOCUMENT_ROOT"] . "/bitrix/modules/rarus.sms4b/classes/general/sms4b.php");

use \Bitrix\Main\Localization\Loc;
Loc::loadLanguageFile(__FILE__);

/**
 * @author AZAREV
 * @version 1.2.0
 */
class Csms4b extends CSms4BitrixWrapper
{
	/**
	 * @var $limit int - ограничение для количества смс в отчетах
	 */
	protected $limit = 5000;

	/**
	 * Выборка данных из БД по фильтру (входящие смс)
	 *
	 * @param $sort array - массив сортировки значений
	 * @param $filter array - фильтр
	 *
	 * @return object - результат выборки obResult
	 */
	public function GetListInc($sort, $filter)
	{
		$res = \Rarus\Sms4b\Sms4bIncTable::getList(array(
			'select'  => array('*'),
			'filter'  => $filter,
			'order'   => $sort,
			'limit' => $this->limit,
		));

		if(!empty($res))
		{
			return $res;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Выборка данных из БД по фильтру
	 *
	 * @param $sort array - массив сортировки значений
	 * @param $filter array - фильтр
	 *
	 * @return object - результат выборки obResult
	 */
	public function GetList($sort, $filter)
	{
		$res = \Rarus\Sms4b\Sms4bTable::getList(array(
			'select'  => array('*'),
			'filter'  => $filter,
			'order'   => $sort,
			'limit' => $this->limit,
		));

		if(!empty($res))
		{
			return $res;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Возвращает данные смс по его ID
	 *
	 * @param $id int - ID
	 *
	 * @return array/bool - результат выборки/false
	 */
	public function GetByID($id)
	{
		$res = $this->GetList(array(), array("ID" => $id));
		$arRes = $res->Fetch();
		if(!empty($arRes))
		{
			return($arRes);
		}
		else
		{
			return false;
		}
	}

	/**
	 * Запись смс в БД
	 *
	 * @param array - массив записываемых SMS
	 *
	 * @return string - LastModified
	 */
	public function ArrayAdd($arParam = array())
	{
		foreach ($arParam as $param)
		{
			$res = \Rarus\Sms4b\Sms4bTable::add(array(
				'GUID' => $param['GUID'],
				'SENDERNAME' => $param['SenderName'],
				'DESTINATION' => $param['Destination'],
				'STARTSEND' => $param['StartSend'],
				'LASTMODIFIED' => $param['LastModified'],
				'STATUS' => $param['Status'],
				'COUNTPART' => $param['CountPart'],
				'SENDPART' => $param['SendPart'],
				'CODETYPE' => $param['CodeType'],
				'TEXT' => $param['TextMessage'],
				'ORDER_ID' => $param['Sale_Order'],
				'POSTING' => $param['Posting'],
				'EVENT_NAME' => $param['Events'],
				'RESULT' => $param['Result'],
			));

			$lastMod = $param['LastModified'];
		}

		if($res->isSuccess())
		{
			return $lastMod;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Запись смс в БД (входящие)
	 *
	 * @param $param array - массив параметров
	 *
	 * @return bool - false в случае неудачи
	 */
	public function AddIncoming($param)
	{
		$res = \Rarus\Sms4b\Sms4bIncTable::add(array(
			'GUID' => $param['GUID'],
			'MOMENT' => $param['Moment'],
			'TIMEOFF' => $param['TimeOff'],
			'SOURCE' => $param['Source'],
			'DESTINATION' => $param['Destination'],
			'CODING' => $param['Coding'],
			'BODY' => $param['Body'],
			'TOTAL' => $param['Total'],
			'PART' => $param['Part'],
		));

		if($res->isSuccess())
		{
			return true;
		}
		else
		{
			return false;
		}
	}
}

global $SMS4B;
if(!is_object($SMS4B))
{
	$SMS4B = new Csms4b();
}
