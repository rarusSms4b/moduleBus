<?
include($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_before.php');
use \Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;

Loc::loadLanguageFile(__FILE__);
CModule::IncludeModule('rarus.sms4b');
global $SMS4B;

$request = Application::getInstance()->getContext()->getRequest();
$macro = $userPhone = $smsAdminText = $smsText = '';
$error = array();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $arFilter = array(
        'ID' => (int)$request->getQuery('eventID'),
        'LID' => 'ru'
    );

    $obEvents = CEventType::GetList($arFilter);
    if ($arEvent = $obEvents->Fetch()) {
        $macro = $arEvent['DESCRIPTION'];
        //проверяем, есть ли шаблон СМС для
        $smsText = $SMS4B->GetEventTemplate('SMS4B_' . $arEvent['EVENT_NAME'], $request->getQuery('site'), 'USER');
        $userPhone = $smsText['EMAIL_TO'];
        $smsAdminText = $SMS4B->GetEventTemplate('SMS4B_' . $arEvent['EVENT_NAME'], $request->getQuery('site'), 'ADMIN');
    }
    ?>
    <form method="POST" action="/bitrix/admin/sms4b_main_addtemplate.php" id="myForm">
        <table>
            <tr>
                <td><?= Loc::getMessage('SMS4B_MAIN_MACRO'); ?></td>
                <td>
                    <pre><?= $macro ?></pre>
                </td>
            </tr>
            <tr>
                <td><label><?= Loc::getMessage('SMS4B_MAIN_PHONE'); ?></label></td>
                <td><input type="text" name="phone" value="<?= $userPhone ?>"><span id="SPAN_ID"></span>
            </tr>
            <tr>
                <td><label><?= Loc::getMessage('SMS4B_MAIN_USER_SMS') ?></label></td>
                <td><textarea name="smstemplate"
                              style="height: 78px; width: 300px;"><?= $smsText['MESSAGE'] ?></textarea></td>
            </tr>
            <tr>
                <td><label><?= Loc::getMessage('SMS4B_MAIN_ADMIN_SMS') ?></label></td>
                <td><textarea name="smsadmintemplate"
                              style="height: 78px; width: 300px;"><?= $smsAdminText['MESSAGE'] ?></textarea></td>
            </tr>
            <input type="hidden" name="eventType" value="<?= $arEvent['EVENT_NAME'] ?>">
            <input type="hidden" name="site" value="<?= $request->getQuery('site') ?>">
    </form>

    <script>BX.hint_replace(BX('SPAN_ID'), '<?echo CUtil::JSEscape(Loc::getMessage('SMS4B_MAIN_SPAN_ID'))?>');</script>

    <?
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userSms = trim($request->getPost('smstemplate'));
    $adminSms = trim($request->getPost('smsadmintemplate'));
    $userPhone = trim($request->getPost('phone'));
    $eventType = trim($request->getPost('eventType'));
    $site = trim($request->getPost('site'));

    //find tempalte
    $arFilter = array(
        'TYPE_ID' => 'SMS4B_' . $eventType
    );

    $obTemplate = CEventType::GetList($arFilter);
    $isTemplate = false;
    if ($arTemplate = $obTemplate->Fetch()) {
        $isTemplate = true;
    }

    $smsTemplate = $SMS4B->GetEventTemplate('SMS4B_' . $eventType, $site, 'SMS4B_USER');
    $smsAdminTemplate = $SMS4B->GetEventTemplate('SMS4B_' . $eventType, $site, 'SMS4B_ADMIN');

    //нет текста для обоих сообщений. Удаляем тип
    if (empty($userSms) && empty($adminSms)) {
        CEventMessage::Delete($smsTemplate['ID']);
        CEventMessage::Delete($smsAdminTemplate['ID']);
        $error[] = Loc::getMessage('SMS4B_MAIN_TEMPLATE_DELETE_ADMIN');
        CEventType::Delete('SMS4B_' . $eventType);
        $error[] = Loc::getMessage('SMS4B_MAIN_EVENT_DELETE') . 'SMS4B_' . $eventType;
    } //add
    elseif (!$isTemplate) {
        $arFilter = array(
            'TYPE_ID' => $eventType,
            'LID' => 'ru'
        );
        $obTemplate = CEventType::GetList($arFilter);
        if ($arTemplate = $obTemplate->Fetch()) {
            $arFields = array(
                'LID' => 'ru',
                'EVENT_NAME' => 'SMS4B_' . $eventType,
                'NAME' => $arTemplate['NAME'],
                'DESCRIPTION' => $arTemplate['DESCRIPTION']
            );

            if (!CEventType::Add($arFields)) {
                $error[] = Loc::getMessage('SMS4B_MAIN_EVENT_ADD_ERROR') . $el->LAST_ERROR;
            } else {
                $error[] = Loc::getMessage('SMS4B_MAIN_EVENT_ADD') . $eventType;
                if (!empty($userSms)) {
                    $arr = array(
                        'ACTIVE' => 'Y',
                        'EVENT_NAME' => 'SMS4B_' . $eventType,
                        'LID' => $site,
                        'EMAIL_FROM' => 'SMS4B_USER',
                        'EMAIL_TO' => $phone,
                        'SUBJECT' => $arTemplate['NAME'],
                        'BODY_TYPE' => 'text',
                        'MESSAGE' => $userSms
                    );

                    $obSMSTemplate = new CEventMessage;
                    if ($obSMSTemplate->Add($arr)) {
                        $error[] = Loc::getMessage('SMS4B_MAIN_TEMPLATE_ADD');
                    } else {
                        $error[] = Loc::getMessage('SMS4B_MAIN_TEMPLATE_ADD_ERROR');
                    }
                }
                if (!empty($adminSms)) {
                    $arr = array(
                        'ACTIVE' => 'Y',
                        'EVENT_NAME' => 'SMS4B_' . $eventType,
                        'LID' => $site,
                        'EMAIL_FROM' => 'SMS4B_ADMIN',
                        'BODY_TYPE' => 'text',
                        'SUBJECT' => $arTemplate['NAME'],
                        'MESSAGE' => $adminSms
                    );
                    $obSMSTemplate = new CEventMessage;
                    if ($obSMSTemplate->Add($arr)) {
                        $error[] = Loc::getMessage('SMS4B_MAIN_TEMPLATE_ADD_ADMIN');
                    } else {
                        $error[] = Loc::getMessage('SMS4B_MAIN_TEMPLATE_ADD_ADMIN_ERROR');
                    }
                }
            }
        }
    } elseif ($isTemplate) {
        if (empty($userSms) && !empty($smsTemplate)) {
            CEventMessage::Delete($smsTemplate['ID']);
            $error[] = Loc::getMessage('SMS4B_MAIN_TEMPLATE_DELETE');
        } elseif (!empty($userSms)) {
            $em = new CEventMessage;
            $arFields = Array(
                'EMAIL_TO' => $userPhone,
                'MESSAGE' => $userSms
            );
            if (empty($smsTemplate['ID'])) {
                $arr = array(
                    'ACTIVE' => 'Y',
                    'EVENT_NAME' => 'SMS4B_' . $eventType,
                    'LID' => $site,
                    'EMAIL_FROM' => 'SMS4B_USER',
                    'EMAIL_TO' => $phone,
                    'BODY_TYPE' => 'text',
                    'SUBJECT' => $arTemplate['NAME'],
                    'MESSAGE' => $userSms
                );
                $obSMSTemplate = new CEventMessage;
                if ($obSMSTemplate->Add($arr)) {
                    $error[] = Loc::getMessage('SMS4B_MAIN_TEMPLATE_ADD');
                } else {
                    $error[] = Loc::getMessage('SMS4B_MAIN_TEMPLATE_ADD_ERROR');
                }
            } else {
                if ($em->Update($smsTemplate['ID'], $arFields)) {
                    $error[] = Loc::getMessage('SMS4B_MAIN_TEMPLATE_UPDATE');
                } else {
                    $error[] = Loc::getMessage('SMS4B_MAIN_TEMPLATE_UPDATE_ERROR');
                }
            }
        }
        if (empty($adminSms) && !empty($smsAdminTemplate)) {
            CEventMessage::Delete($smsAdminTemplate['ID']);
            $error[] = Loc::getMessage('SMS4B_MAIN_TEMPLATE_DELETE_ADMIN');
        } elseif (!empty($adminSms)) {
            $em = new CEventMessage;
            $arFields = Array(
                'MESSAGE' => $adminSms
            );
            if (empty($smsAdminTemplate['ID'])) {
                $arr = array(
                    'ACTIVE' => 'Y',
                    'EVENT_NAME' => 'SMS4B_' . $eventType,
                    'LID' => $site,
                    'EMAIL_FROM' => 'SMS4B_ADMIN',
                    'BODY_TYPE' => 'text',
                    'SUBJECT' => $arTemplate['NAME'],
                    'MESSAGE' => $adminSms
                );
                $obSMSTemplate = new CEventMessage;
                if ($obSMSTemplate->Add($arr)) {
                    $error[] = Loc::getMessage('SMS4B_MAIN_TEMPLATE_ADD_ADMIN');
                } else {
                    $error[] = Loc::getMessage('SMS4B_MAIN_TEMPLATE_ADD_ADMIN_ERROR');
                }
            } else {
                if ($em->Update($smsAdminTemplate['ID'], $arFields)) {
                    $error[] = Loc::getMessage('SMS4B_MAIN_TEMPLATE_UPDATE_ADMIN');
                } else {
                    $error[] = Loc::getMessage('SMS4B_MAIN_TEMPLATE_UPDATE_ADMIN_ERROR');
                }
            }
        }
    }
    echo implode('<br/>', $error);
}
?>