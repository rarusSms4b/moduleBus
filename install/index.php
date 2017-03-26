<?
if (class_exists('rarus_sms4b')) {
    return;
}

use \Bitrix\Main\Localization\Loc;
/**
 * Класс установки модуля
 */
class rarus_sms4b extends CModule
{
    var $MODULE_ID = 'rarus.sms4b';
    var $MODULE_VERSION;
    var $MODULE_VERSION_DATE;
    var $MODULE_NAME;
    var $MODULE_DESCRIPTION;
    var $MODULE_CSS;
    var $MODULE_GROUP_RIGHTS = 'Y';
    var $PARTNER_NAME;

    /**
     * Конструктор
     */
    public function __construct()
    {
        $arModuleVersion = array();
        $path = str_replace("\\", '/', __FILE__);
        Loc::loadLanguageFile($path);
        $path = substr($path, 0, strlen($path) - strlen('/index.php'));
        include($path . '/version.php');

        if (is_array($arModuleVersion) && array_key_exists('VERSION', $arModuleVersion))
        {
            $this->MODULE_VERSION = $arModuleVersion['VERSION'];
            $this->MODULE_VERSION_DATE = $arModuleVersion['VERSION_DATE'];
            $this->PARTNER_NAME = GetMessage('SMS4B_MAIN_COMPANY_NAME');
            $this->PARTNER_URI = 'http://rarus-crimea.ru/web/?utm_source=rarus_sms4b&utm_medium=module&utm_campaign=sms4b';
        }
        else
        {
            $this->MODULE_VERSION = SMS4B_VERSION;
            $this->MODULE_VERSION_DATE = SMS4B_VERSION_DATE;
        }

        $this->MODULE_NAME = GetMessage('SMS4B_MAIN_INSTALL_NAME');
        $this->MODULE_DESCRIPTION = GetMessage('SMS4B_MAIN_INSTALL_DESCRIPTION');
    }

    public function DoInstall()
    {
        global $APPLICATION, $step;

        $POST_RIGHT = $GLOBALS['APPLICATION']->GetGroupRight($this->MODULE_ID);

        if ($POST_RIGHT === 'W') {
            $step = (int)$step;
            if ($step < 2) {
                $GLOBALS['APPLICATION']->IncludeAdminFile(Loc::getMessage('SMS4B_MAIN_INST_INST_TITLE'),
                    $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/step1.php');
            } elseif ($step == 2) {
                $this->InstallDB();
                $this->InstallEvents();
                $this->InstallFiles();
                if (\Bitrix\Main\Loader::includeModule('bizproc')) {
                    $this->InstallActivity();
                }
                if (\Bitrix\Main\Loader::includeModule('tasks')) {
                    $id = CAgent::AddAgent(
                        'CSms4BitrixWrapper::TaskDeadline();',
                        $this->MODULE_ID,
                        'N',
                        600,
                        '',
                        'Y',
                        \Bitrix\Main\Type\DateTime::createFromTimestamp(strtotime('+1 minutes'))
                    );
                    if (!empty($id)) {
                        COption::SetOptionString($this->MODULE_ID, 'deadline_agent_id', $id);
                    }
                }

                $APPLICATION->IncludeAdminFile(Loc::getMessage('SMS4B_MAIN_INST_INST_TITLE'),
                    $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/step2.php');
            }
        }
    }

    public function DoUninstall()
    {
        global $step;

        $POST_RIGHT = $GLOBALS['APPLICATION']->GetGroupRight($this->MODULE_ID);
        if ($POST_RIGHT === 'W') {
            $step = (int)$step;
            if ($step < 2) {
                $GLOBALS['APPLICATION']->IncludeAdminFile(Loc::getMessage('SMS4B_MAIN_INST_UNINST_TITLE'),
                    $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/unstep1.php');
            } elseif ($step == 2) {
                $this->UnInstallDB(array(
                    'save_tables' => $_REQUEST['save_tables']
                ));
                $this->UnInstallEvents($_REQUEST['save_templates']);
                $this->UnInstallFiles();

                if (\Bitrix\Main\Loader::includeModule('bizproc')) {
                    $this->UnInstallActivity();
                }

                if (\Bitrix\Main\Loader::includeModule('tasks')) {
                    CAgent::RemoveModuleAgents($this->MODULE_ID);
                }

                $GLOBALS['errors'] = $this->errors;

                $GLOBALS['APPLICATION']->IncludeAdminFile(Loc::getMessage('SMS4B_MAIN_INST_UNINST_TITLE'),
                    $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/unstep2.php');
            }
        }
    }

    /**
     * Создание таблиц
     *
     * @param array $arParams - массив параметров
     *
     * @return bool - результат создания таблиц
     */
    public function InstallDB($arParams = array())
    {

        global $DB;
        $this->errors = $DB->RunSQLBatch($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/db/' . strtolower($DB->type) . '/install.sql');

        if ($this->errors !== false) {
            $GLOBALS['APPLICATION']->ThrowException(implode('<br>', $this->errors));
            return false;
        } else {
            RegisterModule($this->MODULE_ID);
            CModule::IncludeModule($this->MODULE_ID);
            return true;
        }
    }

    /**
     * Удаление таблиц
     *
     * @param array $arParams - массив параметров
     *
     * @return bool - результат удаления таблиц
     */
    public function UnInstallDB($arParams = array())
    {
        global $DB;
        $this->errors = false;

        if (!array_key_exists('save_tables', $arParams) || ($arParams['save_tables'] !== 'Y')) {
            //kick current user options
            COption::RemoveOption($this->MODULE_ID, '');
            //drop tables
            $this->errors = $DB->RunSQLBatch($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/db/' . strtolower($DB->type) . '/uninstall.sql');
            //drop files
            $strSql = "SELECT ID FROM b_file WHERE MODULE_ID='" . $this->MODULE_ID . "'";
            $rsFile = $DB->Query($strSql, false, 'File: ' . __FILE__ . '<br>Line: ' . __LINE__);
            while ($arFile = $rsFile->Fetch()) {
                CFile::Delete($arFile['ID']);
            }
        }

        UnRegisterModule($this->MODULE_ID);
        if ($this->errors !== false) {
            $GLOBALS['APPLICATION']->ThrowException(implode('<br>', $this->errors));
            return false;
        }

        return true;
    }

    /**
     * Регистрация обработчиков событий
     *
     * @return bool - результат регистрации обработчиков
     */
    public function InstallEvents()
    {
        RegisterModuleDependences('main', 'OnBeforeEventAdd', $this->MODULE_ID, 'Csms4b', 'Events');
        RegisterModuleDependences('subscribe', 'BeforePostingSendMail', $this->MODULE_ID, 'Csms4b', 'EventsPosting');
        if (CModule::IncludeModule('sale')) {
            RegisterModuleDependences('main', 'OnAdminListDisplay', $this->MODULE_ID, 'Csms4b',
                'OnAdminListDisplayHandler');
            RegisterModuleDependences('main', 'OnBeforeProlog', $this->MODULE_ID, 'Csms4b', 'OnBeforePrologHandler');
            RegisterModuleDependences('sale', 'OnBeforeSaleShipmentSetFields', $this->MODULE_ID, 'Csms4b', 'SaleShipmentHandler');
        }
        if (CModule::IncludeModule('tasks')) {
            RegisterModuleDependences('tasks', 'OnTaskAdd', $this->MODULE_ID, 'Csms4b', 'TaskAdded', 10001);
            RegisterModuleDependences('tasks', 'OnTaskUpdate', $this->MODULE_ID, 'Csms4b', 'TaskUpdated', 10001);
            RegisterModuleDependences('tasks', 'OnBeforeTaskDelete', $this->MODULE_ID, 'Csms4b', 'BeforeTaskDeleted',
                10001);
            RegisterModuleDependences('tasks', 'OnAfterCommentAdd', $this->MODULE_ID, 'Csms4b', 'AddNewCommentTask', 10001,
                false, array('OnAfterCommentAdd', 'new_comment_task'));
        }

        //Регистрируем обработчики для событий модуля CRM
        if (CModule::IncludeModule('crm')) {
            //Обработчик для событий лидов
            RegisterModuleDependences('crm', 'OnAfterCrmLeadAdd', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler', 10001,
                false, array('OnAfterCrmLeadAdd', 'add_lead_crm'));
            RegisterModuleDependences('crm', 'OnAfterCrmLeadUpdate', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler', 10001,
                false, array('OnAfterCrmLeadUpdate', 'update_lead_crm'));
            RegisterModuleDependences('crm', 'OnBeforeCrmLeadDelete', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler',
                10001, false, array('OnBeforeCrmLeadDelete', 'delete_lead_crm'));


            //Обработчик для событий контактов
            RegisterModuleDependences('crm', 'OnAfterCrmContactAdd', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler', 10001,
                false, array('OnAfterCrmContactAdd', 'add_contact_crm'));
            RegisterModuleDependences('crm', 'OnAfterCrmContactUpdate', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler',
                10001, false, array('OnAfterCrmContactUpdate', 'update_contact_crm'));
            RegisterModuleDependences('crm', 'OnAfterCrmContactDelete', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler',
                10001, false, array('OnAfterCrmContactDelete', 'remove_contact_crm'));

            //Обработчик для событий сделок
            RegisterModuleDependences('crm', 'OnAfterCrmDealAdd', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler', 10001,
                false, array('OnAfterCrmDealAdd', 'add_deal_crm'));
            RegisterModuleDependences('crm', 'OnAfterCrmDealUpdate', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler', 10001,
                false, array('OnAfterCrmDealUpdate', 'update_deal_crm'));
            RegisterModuleDependences('crm', 'OnAfterCrmDealDelete', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler', 10001,
                false, array('OnAfterCrmDealDelete', 'delete_deal_crm'));

            //Обработчик для событий дел
            RegisterModuleDependences('calendar', 'OnRemindEvent', $this->MODULE_ID, 'Csms4b', 'OnRemindEvent', 10001,
                false, array('OnRemindEvent', 'remind_event_crm'));

            //Обработчик событий смены статуса
            RegisterModuleDependences('crm', 'OnBeforeCrmLeadUpdate', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler',
                10001, false, array('OnBeforeCrmLeadUpdate', 'change_stat_lead_crm'));
            RegisterModuleDependences('crm', 'OnBeforeCrmDealUpdate', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler',
                10001, false, array('OnBeforeCrmDealUpdate', 'change_stat_deal_crm'));

        }
        //Регистрируем обработчик для телефонии
        if (CModule::IncludeModule('voximplant')) {
            RegisterModuleDependences('voximplant', 'onCallEnd', $this->MODULE_ID, 'Csms4b', 'AutoAnswering', 100);
        }

        //install templates for events
        require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/events.php');
        return true;
    }

    /**
     * Удаление обработчиков событий
     *
     * @param mixed $saveTemplates - флаг удаления шаблонов
     *
     * @return bool - результат удаления обработчиков
     */
    public function UnInstallEvents($saveTemplates = false)
    {
        UnRegisterModuleDependences('main', 'OnBeforeEventAdd', $this->MODULE_ID, 'Csms4b', 'Events');
        UnRegisterModuleDependences('subscribe', 'BeforePostingSendMail', $this->MODULE_ID, 'Csms4b', 'EventsPosting');

        if (CModule::IncludeModule('sale')) {
            UnRegisterModuleDependences('main', 'OnAdminListDisplay', $this->MODULE_ID, 'Csms4b',
                'OnAdminListDisplayHandler');
            UnRegisterModuleDependences('main', 'OnBeforeProlog', $this->MODULE_ID, 'Csms4b', 'OnBeforePrologHandler');
            UnRegisterModuleDependences('sale', 'OnBeforeSaleShipmentSetFields', $this->MODULE_ID, 'Csms4b', 'SaleShipmentHandler');
        }
        if (CModule::IncludeModule('tasks')) {
            UnRegisterModuleDependences('tasks', 'OnTaskAdd', $this->MODULE_ID, 'Csms4b', 'TaskAdded');
            UnRegisterModuleDependences('tasks', 'OnTaskUpdate', $this->MODULE_ID, 'Csms4b', 'TaskUpdated');
            UnRegisterModuleDependences('tasks', 'OnBeforeTaskDelete', $this->MODULE_ID, 'Csms4b', 'BeforeTaskDeleted');
            UnRegisterModuleDependences('tasks', 'OnAfterCommentAdd', $this->MODULE_ID, 'Csms4b', 'AddNewCommentTask', '',
                array('OnAfterCommentAdd', 'new_comment_task'));
        }

        //Удаляем обработчики для событий модуля CRM
        if (CModule::IncludeModule('crm')) {
            //Обработчик для событий лидов
            UnRegisterModuleDependences('crm', 'OnAfterCrmLeadAdd', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler', '',
                array('OnAfterCrmLeadAdd', 'add_lead_crm'));
            UnRegisterModuleDependences('crm', 'OnAfterCrmLeadUpdate', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler', '',
                array('OnAfterCrmLeadUpdate', 'update_lead_crm'));
            UnRegisterModuleDependences('crm', 'OnBeforeCrmLeadDelete', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler', '',
                array('OnBeforeCrmLeadDelete', 'delete_lead_crm'));

            //Обработчик для событий контактов
            UnRegisterModuleDependences('crm', 'OnAfterCrmContactAdd', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler', '',
                array('OnAfterCrmContactAdd', 'add_contact_crm'));
            UnRegisterModuleDependences('crm', 'OnAfterCrmContactUpdate', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler',
                '', array('OnAfterCrmContactUpdate', 'update_contact_crm'));
            UnRegisterModuleDependences('crm', 'OnAfterCrmContactDelete', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler',
                '', array('OnAfterCrmContactDelete', 'remove_contact_crm'));

            //Обработчик для событий сделок
            UnRegisterModuleDependences('crm', 'OnAfterCrmDealAdd', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler', '',
                array('OnAfterCrmDealAdd', 'add_deal_crm'));
            UnRegisterModuleDependences('crm', 'OnAfterCrmDealUpdate', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler', '',
                array('OnAfterCrmDealUpdate', 'update_deal_crm'));
            UnRegisterModuleDependences('crm', 'OnAfterCrmDealDelete', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler', '',
                array('OnAfterCrmDealDelete', 'delete_deal_crm'));

            //Обработчик для событий дел
            UnRegisterModuleDependences('calendar', 'OnRemindEvent', $this->MODULE_ID, 'Csms4b', 'OnRemindEvent', '',
                array('OnRemindEvent', 'remind_event_crm'));

            //Обработчик событий смены статуса
            UnRegisterModuleDependences('crm', 'OnBeforeCrmLeadUpdate', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler', '',
                array('OnBeforeCrmLeadUpdate', 'change_stat_lead_crm'));
            UnRegisterModuleDependences('crm', 'OnBeforeCrmDealUpdate', $this->MODULE_ID, 'Csms4b', 'CrmEventsHandler', '',
                array('OnBeforeCrmDealUpdate', 'change_stat_deal_crm'));
        }
        if (CModule::IncludeModule('voximplant')) {
            UnRegisterModuleDependences('voximplant', 'onCallEnd', $this->MODULE_ID, 'Csms4b', 'AutoAnswering');
        }

        //Удаление почтовых событий и шаблонов
        if($saveTemplates !== 'Y')
        {
            $arRes = CEventType::GetList();
            while ($res = $arRes->Fetch()) {
                if (false !== strpos($res['EVENT_NAME'], 'SMS4B_')) {
                    CEventType::Delete($res['EVENT_NAME']);
                    $dbEvent = CEventMessage::GetList($b = 'ID', $order = 'ASC', Array('EVENT_NAME' => $res['EVENT_NAME']));
                    while ($arEvent = $dbEvent->Fetch()) {
                        CEventMessage::Delete($arEvent['ID']);
                    }
                }
            }
        }

        return true;
    }

    /**
     * Копирование файлов
     *
     * @param array $arParams - массив параметров
     * @return bool - результат копирования
     */
    public function InstallFiles($arParams = array())
    {
        CopyDirFiles($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin', true, true);
        CopyDirFiles($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/images',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/images', false, true);
        CopyDirFiles($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/themes',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/themes', false, true);
        CopyDirFiles($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/js',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/js/', false, true);

        if ($_REQUEST['INSTALL_COMPONENTS'] === 'Y') {
            CopyDirFiles($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/components',
                $_SERVER['DOCUMENT_ROOT'] . '/bitrix/components', false, true);
        }

        if ($_REQUEST['INSTALL_DEMO'] === 'Y') {
            $target = $_SERVER['DOCUMENT_ROOT'] . '/sms4b_demo/';
            CopyDirFiles(
                $_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/public',
                $target,
                false,
                true
            );
        }

        if ($_REQUEST['INSTALL_HELP'] === 'Y') {
            CopyDirFiles($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/help',
                $_SERVER['DOCUMENT_ROOT'] . '/bitrix/help/ru/source/service/rarus.sms4b', false, true);
        }
        return true;
    }

    /**
     * Удаление файлов
     *
     * @return bool - результат удаления
     */
    public function UnInstallFiles()
    {
        //admin files
        DeleteDirFiles($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/admin',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/admin');
        //css
        DeleteDirFiles($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/rarus.sms4b/install/themes/.default',
            $_SERVER['DOCUMENT_ROOT'] . '/bitrix/themes/.default');
        //icons
        DeleteDirFilesEx('/bitrix/themes/.default/icons/rarus.sms4b');
        //images
        DeleteDirFilesEx('/bitrix/images/rarus.sms4b');
        //wizard
        DeleteDirFilesEx('/bitrix/wizards/rarus.sms4b');
        //delete js
        DeleteDirFilesEx('/bitrix/js/rarus.sms4b');
        //COMPONENTS
        if ($_REQUEST['SAVE_COMPONENTS'] !== 'Y') {
            DeleteDirFilesEx('/bitrix/components/rarus.sms4b');
        }
        //delete help
        if ($_REQUEST['SAVE_HELP'] !== 'Y') {
            DeleteDirFilesEx('/bitrix/help/ru/source/service/rarus.sms4b');
        }
        //delete demo public part
        if ($_REQUEST['SAVE_DEMO'] !== 'Y') {
            DeleteDirFilesEx('/sms4b_demo');
        }
        return true;
    }

    /**
     * Функция добавления activity для БП
     *
     * @return bool - результат добавления
     */
    public function InstallActivity()
    {
        return CopyDirFiles(
            $_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/modules/' . GetModuleID(__FILE__) . '/install/activity',
            $_SERVER['DOCUMENT_ROOT'] . BX_ROOT . '/activities/custom',
            true,
            true
        );
    }

    /**
     * Функция удаления activity для БП
     *
     * @return bool - результат удаления
     */
    public function UnInstallActivity()
    {
        if (DeleteDirFilesEx(BX_ROOT . '/activities/custom/sms4bactivitytouser')
            && DeleteDirFilesEx(BX_ROOT . '/activities/custom/sms4bactivitytophone')
            && DeleteDirFilesEx(BX_ROOT . '/activities/custom/sms4bactivitytocontact')
            && DeleteDirFilesEx(BX_ROOT . '/activities/custom/sms4bactivitytolead')
        ) {
            return true;
        } else {
            return false;
        }

    }
}