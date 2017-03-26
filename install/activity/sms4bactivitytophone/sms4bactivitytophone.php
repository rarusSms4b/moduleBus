<?php if (!defined('B_PROLOG_INCLUDED') || B_PROLOG_INCLUDED !== true) {
    die();
}

use \Bitrix\Main\Localization\Loc;
Loc::loadLanguageFile(__FILE__);

/**
 * Class CBPSMS4BActivityToPhone
 */
class CBPSMS4BActivityToPhone extends CBPActivity
{
    /**
     * �����������
     *
     * @param $name string - ID ���������� ������-��������
     */
    public function __construct($name)
    {
        parent::__construct($name);
        $this->arProperties = array(
            'smsTo' => 'sms_user_to',
            'smsText' => 'sms_text'
        );
    }

    /**
     * ���������� ������-��������
     *
     * @throws \Bitrix\Main\ObjectException - ���������� �������� ��������
     * @throws \Bitrix\Main\ArgumentException - ���������� ���������� ����������
     *
     * @return string = "Closed"
     */
    public function Execute()
    {
        if (CModule::IncludeModule('rarus.sms4b')) {
            $sms = new Csms4b();
            $sms->sms4bLog(Loc::getMessage('SMS4B_MAIN_BP_START_SEND_TO_PHONE'));
            $sms->sms4bLog(Loc::getMessage('SMS4B_MAIN_GET_PHONE') . $this->smsTo);
            if ($sms->SendSMS($this->smsText, $this->smsTo)) {
                $this->WriteToTrackingService(Loc::getMessage('SMS4B_MAIN_BP_TEXT') . $this->smsText . Loc::getMessage('SMS4B_MAIN_BP_SEND_TO_NUM') . $this->smsTo);
                $sms->sms4bLog(Loc::getMessage('SMS4B_MAIN_BP_TEXT') . $this->smsText . Loc::getMessage('SMS4B_MAIN_BP_SEND_TO_NUM') . $this->smsTo);
            } else {
                $this->WriteToTrackingService(Loc::getMessage('SMS4B_MAIN_BP_TEXT') . $this->smsText . Loc::getMessage('SMS4B_MAIN_BP_NOT_SEND_TO_NUM') . $this->smsTo);
                $sms->sms4bLog(Loc::getMessage('SMS4B_MAIN_BP_TEXT') . $this->smsText . Loc::getMessage('SMS4B_MAIN_BP_NOT_SEND_TO_NUM') . $this->smsTo);
            }
            $sms->sms4bLog(Loc::getMessage('SMS4B_MAIN_BP_END_SEND_TO_PHONE') . "\n");
        }
        return CBPActivityExecutionStatus::Closed;
    }

    /**
     * �������� ����������
     *
     * @param $arTestProperties array - ������ ����������� ��������
     * @param $user - ������ ������������ CBPWorkflowTemplateUser
     *
     * @return array - ������ � �������� ��� ������
     */
    public static function ValidateProperties($arTestProperties = array(), CBPWorkflowTemplateUser $user = null)
    {
        $arErrors = array();
        if (!array_key_exists('smsTo', $arTestProperties) || strlen($arTestProperties['smsTo']) <= 0) {
            $arErrors[] = array(
                'code' => 'NotExist',
                'parameter' => 'smsTo',
                'message' => Loc::getMessage('SMS4B_MAIN_BPMA_EMPTY_PROP1')
            );
        }
        if (!array_key_exists('smsText', $arTestProperties) || strlen($arTestProperties['smsText']) <= 0) {
            $arErrors[] = array(
                'code' => 'NotExist',
                'parameter' => 'smsText',
                'message' => Loc::getMessage('SMS4B_MAIN_BPMA_EMPTY_PROP2')
            );
        }

        return array_merge($arErrors, parent::ValidateProperties($arTestProperties, $user));
    }

    /**
     * �������� ������ � ������
     *
     * @param $documentType array - ��� ���������
     * @param $activityName string - �������� ��������
     * @param $arWorkflowTemplate array - ��������� �������
     * @param $arWorkflowParameters array - ���� ���������
     * @param $arWorkflowVariables array - ����������
     * @param $arCurrentValues array - ������� ������ �� �������� �� �����
     * @param $formName string - ��� �����
     *
     * @return string - �������� �������
     */
    public static function GetPropertiesDialog(
        $documentType,
        $activityName,
        $arWorkflowTemplate,
        $arWorkflowParameters,
        $arWorkflowVariables,
        $arCurrentValues = null,
        $formName = ''
    ) {
        $runtime = CBPRuntime::GetRuntime();
        if (!is_array($arCurrentValues)) {
            $arCurrentValues = array('sms_user_to' => '', 'sms_text' => '');
            $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
            if (is_array($arCurrentActivity['Properties'])) {
                $arCurrentValues['sms_user_to'] = $arCurrentActivity['Properties']['smsTo'];
                $arCurrentValues['sms_text'] = $arCurrentActivity['Properties']['smsText'];
            }
        }

        return $runtime->ExecuteResourceFile(
            __FILE__,
            'properties_dialog.php',
            array(
                'arCurrentValues' => $arCurrentValues,
                'formName' => $formName
            )
        );
    }

    /**
     * ��������� ������ �������
     *
     * @param $documentType array - ��� ���������
     * @param $activityName string - �������� ��������
     * @param $arWorkflowTemplate array - ��������� �������
     * @param $arWorkflowParameters array - ���� ���������
     * @param $arWorkflowVariables array - ����������
     * @param $arCurrentValues array - ������� ������ �� �������� �� �����
     * @param $arErrors array - ������ ������
     *
     * @return bool - ��������� ����������
     */
    public static function GetPropertiesDialogValues(
        $documentType,
        $activityName,
        &$arWorkflowTemplate,
        &$arWorkflowParameters,
        &$arWorkflowVariables,
        $arCurrentValues,
        &$arErrors
    ) {
        $arMap = array(
            'sms_user_to' => 'smsTo',
            'sms_text' => 'smsText'
        );
        $arProperties = array();
        foreach ($arMap as $key => $value) {
            $arProperties[$value] = $arCurrentValues[$key];
        }
        $arErrors = self::ValidateProperties($arProperties,
            new CBPWorkflowTemplateUser(CBPWorkflowTemplateUser::CurrentUser));
        if (count($arErrors) > 0) {
            return false;
        }
        $arCurrentActivity = &CBPWorkflowTemplateLoader::FindActivityByName($arWorkflowTemplate, $activityName);
        $arCurrentActivity['Properties'] = $arProperties;
        return true;
    }
}