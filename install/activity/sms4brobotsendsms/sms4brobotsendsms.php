<?
if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) die();

use \Bitrix\Main\Localization\Loc;

Loc::loadLanguageFile(__FILE__);

/**
 * Class CBPSms4bRobotSendSms
 */
class CBPSms4bRobotSendSms extends CBPActivity
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
        );
    }

    /**
     * Выполнение робота
     *
     * @throws \Bitrix\Main\ObjectException - Исключения создания объектов
     * @throws \Bitrix\Main\ArgumentException - Исключения переданных аргументов
     *
     * @return string = "Closed"
     */
    public function Execute()
    {
        if (!CModule::IncludeModule('rarus.sms4b')) {
            $this->WriteToTrackingService(Loc::getMessage('SMS4B_INCLUDE_MODULE_FAIL_EXEC')
                . '. ' . Loc::getMessage('SMS4B_SMS_NOT_SEND_EXEC'));
            return CBPActivityExecutionStatus::Closed;
        }
        $sms = new Csms4b();
        $phoneNumber = $sms->is_phone($this->getPhoneNumber());

        if ($this->MessageText === '') {
            $this->WriteToTrackingService(Loc::getMessage('SMS4B_EMPTY_TEXT_EXEC')
                . '. ' . Loc::getMessage('SMS4B_SMS_NOT_SEND_EXEC'));
            return CBPActivityExecutionStatus::Closed;
        }
        if ($phoneNumber === false) {
            $this->WriteToTrackingService(Loc::getMessage('SMS4B_INVALID_PHONE_EXEC') . ' ' . "($phoneNumber)"
                . '. ' . Loc::getMessage('SMS4B_SMS_NOT_SEND_EXEC'));
            return CBPActivityExecutionStatus::Closed;
        }

        $senResult = $sms->SendSmsSaveGroup([$phoneNumber => $this->MessageText]);

        if ($senResult[0]['Result'] <= 0) {
            $this->WriteToTrackingService(Loc::getMessage('SMS4B_SMS_NOT_SEND_EXEC')
                . '. ' . \Rarus\Sms4b\Sms4bClient::GetCodeDescription($senResult[0]['Result']));
        } else {
            $this->WriteToTrackingService(Loc::getMessage('SMS4B_SMS_SUCCESS_SEND_EXEC')
                . ' ' . Loc::getMessage('SMS4B_SMS_SEND_WITH_TEXT_EXEC') . $this->MessageText
                . ' ' . Loc::getMessage('SMS4B_SMS_SEND_TO_PHONE_EXEC') . $phoneNumber
            );
        }

        return CBPActivityExecutionStatus::Closed;
    }

    /**
     * Получить телефон для текущего экземпляра сущности (конкретный лид или сделка)
     *
     * @return string
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
                $communications = $this->getCommunicationsFromFM(CCrmOwnerType::Lead, (int)str_replace('LEAD_', '', $documentId[2]));
                break;
        }

        $communications = array_slice($communications, 0, 1);
        return $communications ? $communications[0]['VALUE'] : null;
    }

    /**
     * Получение массива телефонов сделки
     *
     * @param $id int ID сделки
     * @return array
     */
    private function getDealCommunications($id)
    {
        $communications = array();

        $entity = CCrmDeal::GetByID($id);
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
     * Получение массива телефонов из FieldMulti
     *
     * @param $entityTypeId int ID типа сущности (сделка или лид)
     * @param $entityId int ID экземпляра сущности (ID сделки или лида)
     * @return array
     */
    private function getCommunicationsFromFM($entityTypeId, $entityId)
    {
        $entityTypeName = CCrmOwnerType::ResolveName($entityTypeId);
        $communications = array();

        $iterator = CCrmFieldMulti::GetList(
            array('ID' => 'asc'),
            array('ENTITY_ID' => $entityTypeName,
                'ELEMENT_ID' => $entityId,
                'TYPE_ID' => 'PHONE'
            )
        );

        while ($row = $iterator->fetch()) {
            if (empty($row['VALUE']))
                continue;

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
     * @param $user CBPWorkflowTemplateUser объект пользователя CBPWorkflowTemplateUser
     *
     * @return array - массив с ошибками или пустой
     */
    public static function ValidateProperties($arTestProperties = array(), CBPWorkflowTemplateUser $user = null)
    {
        $arErrors = array();

        if (empty($arTestProperties["MessageText"])) {
            $arErrors[] = array("code" => "NotExist", "parameter" => "MessageText", "message" => Loc::getMessage('SMS4B_EMPTY_TEXT'));
        }

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
     * @return string - Свойства диалога
     */
    public static function GetPropertiesDialog($documentType, $activityName, $arWorkflowTemplate, $arWorkflowParameters, $arWorkflowVariables, $arCurrentValues = null, $formName = "", $popupWindow = null, $siteId = '')
    {
        if (!CModule::IncludeModule("crm"))
            return '';

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
     * @return bool
     */
    public static function GetPropertiesDialogValues($documentType, $activityName, &$arWorkflowTemplate, &$arWorkflowParameters, &$arWorkflowVariables, $arCurrentValues, &$arErrors)
    {
        $arErrors = Array();

        $arProperties = array(
            'MessageText' => (string)$arCurrentValues["message_text"],
        );

        $arErrors = self::ValidateProperties($arProperties, new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser));
        if (count($arErrors) > 0)
            return false;

        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $arCurrentActivity["Properties"] = $arProperties;

        return true;
    }
}