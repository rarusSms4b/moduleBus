<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use \Bitrix\Main\Localization\Loc;
use Bitrix\Main\Loader;

Loc::loadLanguageFile(__FILE__);

/**
 * Class CBPSms4bRobotSendSms
 */
class CBPSms4bRobotSendDeferredSms extends CBPActivity
{
    /**
     * Конструктор
     *
     * @param $name string - ID экземпляра бизнес-процесса
     */
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = array(
            "Title" => "",
            "MessageText" => '',
            'StartSend' => ''
        );
    }

    /**
     * Выполнение бизнес-процесса
     *
     * @throws \Bitrix\Main\ObjectException
     * @throws \Bitrix\Main\ArgumentException
     *
     * @return string = "Closed"
     */
    public function Execute()
    {
        Loader::includeModule('crm');
        if (!Loader::includeModule('rarus.sms4b')) {
            $this->WriteToTrackingService(Loc::getMessage('SMS4B_MODULE_NOT_FOUND'));
            return CBPActivityExecutionStatus::Closed;
        }

        if (!$this->MessageText || $this->MessageText === '') {
            $this->WriteToTrackingService(Loc::getMessage('SMS4B_EMPTY_TEXT'));
            return CBPActivityExecutionStatus::Closed;
        }

        $sms = new Csms4b();
        $phoneNumber = $this->getPhoneNumber();
        $phoneNumberValid = $sms->is_phone($phoneNumber);

        if ($phoneNumber === null) {
            $this->WriteToTrackingService(Loc::getMessage('SMS4B_EMPTY_PHONE'));
            return CBPActivityExecutionStatus::Closed;
        } elseif ($phoneNumberValid === false) {
            $this->WriteToTrackingService(Loc::getMessage('SMS4B_NOT_VALID'));
            return CBPActivityExecutionStatus::Closed;
        }

        try {
            $result = $sms->SendSmsSaveGroup(array($phoneNumberValid => $this->MessageText), '', $this->StartSend);
        } catch (Rarus\Sms4b\Sms4bException $e) {
            $this->WriteToTrackingService($e->getMessage());
        }

        if ($result[0]['Result'] <= 0) {
            $this->WriteToTrackingService(Loc::getMessage('SMS4B_SMS_NOT_SEND'));
        } else {
            $this->WriteToTrackingService(Loc::getMessage('SMS4B_SMS_SEND',
                array("#TEXT#" => $this->MessageText, "#PHONE#" => $phoneNumberValid)));
        }

        return CBPActivityExecutionStatus::Closed;
    }

    /**
     *
     * Получить номер телефона
     *
     * @return string|null
     */
    private function getPhoneNumber()
    {
        $documentId = $this->GetDocumentId();
        $communications = array();

        switch ($documentId[1]) {
            case 'CCrmDocumentDeal':
                $communications = $this->getDealCommunications((int)str_replace('DEAL_', '', $documentId[2]));
                break;
            case 'CCrmDocumentLead':
                $communications = $this->getCommunicationsFromFM(CCrmOwnerType::Lead,
                    (int)str_replace('LEAD_', '', $documentId[2]));
                break;
        }

        $communications = array_slice($communications, 0, 1);
        return $communications ? $communications[0]['VALUE'] : null;
    }

    /**
     * Получить массив телефонов по сделке
     *
     * @param $id int ID сделки
     * @return array
     */
    private function getDealCommunications($id)
    {
        $communications = array();

        $entity = CCrmDeal::GetByID($id, false);
        if (!$entity) {
            return array();
        }

        $entityContactID = isset($entity['CONTACT_ID']) ? intval($entity['CONTACT_ID']) : 0;
        $entityCompanyID = isset($entity['COMPANY_ID']) ? intval($entity['COMPANY_ID']) : 0;

        if ($entityContactID > 0) {
            $communications = $this->getCommunicationsFromFM(CCrmOwnerType::Contact, $entityContactID);
        }

        if (empty($communications) && $entityCompanyID > 0) {
            $communications = CCrmActivity::GetCompanyCommunications($entityCompanyID, 'PHONE');
        }

        return $communications;
    }

    /**
     * Получить массив телефонов
     *
     * @param $entityTypeId int ID типа сущности
     * @param $entityId int ID инстанса сйщности
     *
     * @return array
     */
    private function getCommunicationsFromFM($entityTypeId, $entityId)
    {
        $entityTypeName = CCrmOwnerType::ResolveName($entityTypeId);
        $communications = array();

        $iterator = CCrmFieldMulti::GetList(
            array('ID' => 'asc'),
            array(
                'ENTITY_ID' => $entityTypeName,
                'ELEMENT_ID' => $entityId,
                'TYPE_ID' => 'PHONE'
            )
        );

        while ($row = $iterator->fetch()) {
            if (empty($row['VALUE'])) {
                continue;
            }

            $communications[] = array(
                'ENTITY_ID' => $entityId,
                'ENTITY_TYPE_ID' => $entityTypeId,
                'ENTITY_TYPE' => $entityTypeName,
                'TYPE' => 'PHONE',
                'VALUE' => $row['VALUE'],
                'VALUE_TYPE' => $row['VALUE_TYPE']
            );
        }

        return $communications;
    }

    /**
     * Проверка переменных
     *
     * @param $arTestProperties array массив проверяемых значений
     * @param $user CBPWorkflowTemplateUser пользователя CBPWorkflowTemplateUser
     *
     * @return array - массив с ошибками или пустой
     */
    public static function ValidateProperties(array $arTestProperties = array(), CBPWorkflowTemplateUser $user = null)
    {
        $arErrors = array();

        if (empty($arTestProperties["MessageText"])) {
            $arErrors[] = array(
                "code" => "NotExist",
                "parameter" => "MessageText",
                "message" => GetMessage("SMS4B_EMPTY_TEXT")
            );
        }

//        if ($arTestProperties["StartSend"] === '') {
//            $arErrors[] = array(
//                "code" => "FailDeferredTime",
//                "parameter" => "StartSend",
//                "message" => GetMessage("SMS4B_DEFERRED_DATETIME_FAIL")
//            );
//        }

        return array_merge($arErrors, parent::ValidateProperties($arTestProperties, $user));
    }

    /**
     * Передача данных в диалог
     *
     * @param $documentType array тип документа
     * @param $activityName string название активити
     * @param $arWorkflowTemplate array параметры шаблона
     * @param $arWorkflowParameters array поля документа
     * @param $arWorkflowVariables array переменные
     * @param $arCurrentValues array входные данные от действий БП ранее
     * @param $formName string имя формы
     *
     * @return string Свойства диалога
     */
    public static function GetPropertiesDialog(
        $documentType,
        $activityName,
        $arWorkflowTemplate,
        $arWorkflowParameters,
        $arWorkflowVariables,
        $arCurrentValues = null,
        $formName = "",
        $popupWindow = null,
        $siteId = ''
    ) {
        if (!Loader::includeModule("crm")) {
            return '';
        }

        $dialog = new \Bitrix\Bizproc\Activity\PropertiesDialog(__FILE__, array(
            'documentType' => $documentType,
            'activityName' => $activityName,
            'workflowTemplate' => $arWorkflowTemplate,
            'workflowParameters' => $arWorkflowParameters,
            'workflowVariables' => $arWorkflowVariables,
            'currentValues' => $arCurrentValues,
            'formName' => $formName,
            'siteId' => $siteId
        ));

        $dialog->setMap(array(
            'MessageText' => array(
                'Name' => Loc::getMessage('SMS4B_MESSAGE_TEXT'),
                'FieldName' => 'message_text',
                'Type' => 'text',
                'Required' => true
            )
        ));

        return $dialog;
    }

    /**
     * Получение данных диалога
     *
     * @param $documentType array тип документа
     * @param $activityName string название активити
     * @param $arWorkflowTemplate array параметры шаблона
     * @param $arWorkflowParameters array поля документа
     * @param $arWorkflowVariables array переменные
     * @param $arCurrentValues array входные данные от действий БП ранее
     * @param $arErrors array массив ошибок
     *
     * @throws \Bitrix\Main\ObjectException
     *
     * @return bool
     */
    public static function GetPropertiesDialogValues(
        $documentType,
        $activityName,
        &$arWorkflowTemplate,
        &$arWorkflowParameters,
        &$arWorkflowVariables,
        &$arCurrentValues,
        &$arErrors
    ) {
        $arErrors = Array();

        Loader::includeModule('rarus.sms4b');
        $sms = new Csms4b();
        $sms->sms4bLog(print_r($arCurrentValues, true));

        $sms->sms4bLog('$documentType = ' . print_r($documentType, true));
        $sms->sms4bLog('$activityName = ' . print_r($activityName, true));
        $sms->sms4bLog('$arWorkflowTemplate = ' . print_r($arWorkflowTemplate, true));
        $sms->sms4bLog('$arWorkflowParameters = ' . print_r($arWorkflowParameters, true));
        $sms->sms4bLog('$arWorkflowVariables = ' . print_r($arWorkflowVariables, true));
        $sms->sms4bLog('$arErrors = ' . print_r($arErrors, true));


        $arProperties = array(
            'MessageText' => (string)$arCurrentValues['message_text'],
            'StartSend' => self::getDateStart(
                $arCurrentValues['delay_type'],
                $arCurrentValues['delay_value'],
                $arCurrentValues['delay_value_type'])
        );

        $arCurrentValues['delay_value'] = '';

        $arErrors = self::ValidateProperties($arProperties,
            new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser));
        if (count($arErrors) > 0) {
            return false;
        }

        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $arCurrentActivity["Properties"] = $arProperties;

        return true;
    }

    /**
     * Конвертер даты из строк в
     *
     * @param $delayType string ти
     * @param $delayValue string название активити
     * @param $delayValueType string параметры шаблона
     *
     * @throws \Bitrix\Main\ObjectException
     *
     * @return string
     */
    private static function getDateStart($delayType, $delayValue, $delayValueType)
    {
        //хак, передаваемые интервалы времени имеют типы 'i' - минуты, 'h' - часы, 'd' - дни
        //которые не поддерживаются конструктором DateInterval
        $dateInterval = new \DateInterval('P0D');
        $now = new \Bitrix\Main\Type\DateTime();

        if ($delayType === 'after' && $delayValue !== 0) {
            $dateInterval->$delayValueType = (int)$delayValue;
            $dateStart = $now->add($dateInterval);
            return $dateStart->toString();
        }

        if ($delayType === 'before') {

        }
    }
}