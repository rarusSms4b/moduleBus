<?
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_before.php');

use Bitrix\Main\Localization\Loc;
use Bitrix\Main\Application;

Loc::loadLanguageFile(__FILE__);

$module_id = 'rarus.sms4b';
$gmt = $timestampStartUp = $numbersForSendCount = $period = $sender = $destination = 0;
$errorMessages = '';
$arPhonesMessages = $res = $results_of_package_send = $arPhones = array();
$requestData = Application::getInstance()->getContext()->getRequest();

if ($GLOBALS['APPLICATION']->GetGroupRight('rarus.sms4b') < 'R') {
    $GLOBALS['APPLICATION']->AuthForm(Loc::getMessage('SMS4B_MAIN_SMS4B_MAIN_ACCESS_DENIED'));
}

global $SMS4B;

$arTime = localtime(time(), true);

\Bitrix\Main\Page\Asset::getInstance()->addJs('/bitrix/js/' . $module_id . '/jquery.js');
\Bitrix\Main\Page\Asset::getInstance()->addJs('/bitrix/js/' . $module_id . '/sms4b_sendsms.js');
$GLOBALS['APPLICATION']->SetAdditionalCSS('/bitrix/js/' . $module_id . '/css/sms4b_sendsms.css');


$arResult['RESULT_MESSAGE']['TYPE'] = '';
$arsGmt = $SMS4B::getTimeZone();

$arResult['GMT_CONTROL'] .= '<select size="1" name="gmt" id="gmtControl">';
foreach ((array)$arsGmt as $gmtkey => $gmtval) {
    $arResult['GMT_CONTROL'] .= '<option value="' . $gmtkey . '" ' . ((COption::GetOptionString('rarus.sms4b',
                'gmt') == $gmtkey) ? ' selected ' : '') . '>' . $gmtval . '</option>';
}
$arResult['GMT_CONTROL'] .= '</select>';

if ($SMS4B->LastError == '' && $SMS4B->GetSOAP('AccountParams', array('SessionID' => $SMS4B->GetSID())) === true) {

    if ($SMS4B->arBalance['Rest'] < 0.1) {
        $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
        $arResult['RESULT_MESSAGE']['MESSAGE'] = Loc::getMessage('SMS4B_MAIN_SMS4B_MAIN_NO_MESSAGES') . '<br>';
        $arResult['CAN_SEND'] = 'N';
    } else {
        $arResult['BALANCE'] = $SMS4B->arBalance['Rest'];
        $arResult['ADRESSES'] = $SMS4B->arBalance['Addresses'];

        if (strlen($requestData->getPost('apply')) > 0) {
            //take data entered by user
            $sender = htmlspecialchars($requestData->getPost('sender_number'));
            $message = $requestData->getPost('message');

            //need message about sending?
            $request = ($requestData->getPost('reply') == 'on') ? 0 : 1;

            //checking and setting new def address
            if ($requestData->getPost('def_sender') == 'Y') {
                if (in_array($sender,
                        $arResult['ADRESSES'], false) && $GLOBALS['APPLICATION']->GetGroupRight('rarus.sms4b') >= 'W'
                ) {
                    COption::SetOptionString('rarus.sms4b', 'defsender', $sender);
                    $arResult['RESULT_MESSAGE']['TYPE_DEF'] = 'CHANGING_DEF_SENDER_NUMBER';
                    $arResult['RESULT_MESSAGE']['MESSAGE_DEF'] = Loc::getMessage('SMS4B_MAIN_S_NAME') . "\"" . $sender . "\"" . Loc::getMessage('SMS4B_MAIN_NEW_DEF_NUM');
                } else {
                    $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
                    $arResult['RESULT_MESSAGE']['MESSAGE'] = Loc::getMessage('SMS4B_MAIN_NOT_IN_LIST');
                }
            }

            //  ,
            $destination = $SMS4B->parse_numbers($requestData->getPost('destination_number'));
            $numbersForSendCount = count($destination);
            //
            $arResult['DOUBLED_NUMBERS'] = $SMS4B->doubled_numbers;

            $dataFieldError = false;

            //   SMS   100   *SMS-Test*
            if ($arResult['BALANCE'] > 100 && in_array('SMS-TEST', $arResult['ADRESSES'], false)) {
                $dataFieldError = true;
                $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
                $arResult['RESULT_MESSAGE']['MESSAGE'][] = Loc::getMessage('SMS4B_MAIN_BLOCK_SMS') . " <a href = \"/office/symbol_name_request.php\" target=\"_blank\">" . Loc::getMessage('SMS4B_MAIN_ORDER_SMS_NAME') . '</a>';
            }

            if ($sender == '' || !in_array($sender, $arResult['ADRESSES'], false)) {
                $dataFieldError = true;
                $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
                $arResult['RESULT_MESSAGE']['MESSAGE'][] = Loc::getMessage('SMS4B_MAIN_ERROR_NOT_SET_SENDER_NUMBER');
            }
            //
            if ($numbersForSendCount == 0 || $requestData->getPost('destination_number') == Loc::getMessage('SMS4B_MAIN_DEST_COMMENT')) {
                $dataFieldError = true;
                $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
                $arResult['RESULT_MESSAGE']['MESSAGE'][] = Loc::getMessage('SMS4B_MAIN_ERROR_NOT_SET_DEST_NUMBERS');
            }
            //
            if ($message == '' || $requestData->getPost('message') == Loc::getMessage('SMS4B_MAIN_TEXT_COMMENT')) {
                $dataFieldError = true;
                $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
                $arResult['RESULT_MESSAGE']['MESSAGE'][] = Loc::getMessage('SMS4B_MAIN_ERROR_NOT_SET_TEXT');
            } elseif (isset($SMS4B->sms_sym_count) && $SMS4B->sms_sym_count != '' && strlen($message) > $SMS4B->sms_sym_count) {
                $dataFieldError = true;
                $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
                $arResult['RESULT_MESSAGE']['MESSAGE'][] = Loc::getMessage('SMS4B_MAIN_ERROR_BIG_TEXT');
            }


            if (!is_numeric($requestData->getPost('gmt'))) {
                $dataFieldError = true;
                $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
                $arResult['RESULT_MESSAGE']['MESSAGE'][] = Loc::getMessage('SMS4B_MAIN_ERROR_GMT');
            } else {
                $arTime = localtime(time(), true);

                $gmt = htmlspecialchars($requestData->getPost('gmt'));

                if ($arTime['tm_isdst'] > 0) {
                    ++$gmt;
                }
            }

            // greenwheech timestamp
            $greenWeechTimeStamp = mktime(gmdate('H'), gmdate('i'), gmdate('s'), gmdate('n'), gmdate('j'), gmdate('Y'));

            //
            if ($requestData->getPost('BEGIN_SEND_AT')) {
                $startUp = '';
            } else {
                //..    ,      59
                if (strlen($requestData->getPost('BEGIN_SEND_AT')) == 16) {
                    $send_at = $requestData->getPost('BEGIN_SEND_AT') . ':30';
                } else {
                    $send_at = $requestData->getPost('BEGIN_SEND_AT');
                }

                $startUp = $SMS4B->GetFormatDateForSmsForm($send_at, $gmt);

                //
                if ($startUp == -1) {
                    $dataFieldError = true;
                    $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
                    $arResult['RESULT_MESSAGE']['MESSAGE'][] = Loc::getMessage('SMS4B_MAIN_ERROR_BEGIN_SEND');
                }

                $timestampStartUp = MakeTimeStamp($send_at);
                $currTimeStamp = $greenWeechTimeStamp + ($gmt * 3600);
                $startUp = date('Ymd H:i:s', $timestampStartUp + 1);

                //     10
                $timeX = $timestampStartUp - (86400 * 10);
                if ($timeX > $currTimeStamp) {
                    $dataFieldError = true;
                    $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
                    $arResult['RESULT_MESSAGE']['MESSAGE'][] = Loc::getMessage('SMS4B_MAIN_ERROR_BEGIN_SEND_TWO');
                }
            }

            //
            if ($requestData->getPost('ACTIVE_DATE_ACTUAL') !== 'Y' || $requestData->getPost('DATE_ACTUAL')) {
                $dateActual = '';
            } else {
                if (strlen($requestData->getPost('DATE_ACTUAL')) == 16) {
                    $act_date = $requestData->getPost('DATE_ACTUAL') . ':30';
                } else {
                    $act_date = $requestData->getPost('DATE_ACTUAL');
                }

                $dateActual = $SMS4B->GetFormatDateForSmsForm($act_date, $gmt);

                //
                if ($dateActual == -1) {
                    $dataFieldError = true;
                    $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
                    $arResult['RESULT_MESSAGE']['MESSAGE'][] = Loc::getMessage('SMS4B_MAIN_ERROR_ACTUAL_DATE');
                }

                $timestampDateActual = MakeTimeStamp($act_date);
                //
                $currTimeStamp = $greenWeechTimeStamp + ($gmt * 3600);

                //     ,
                if ($timestampDateActual < $currTimeStamp) {
                    $dataFieldError = true;
                    $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
                    $arResult['RESULT_MESSAGE']['MESSAGE'][] = Loc::getMessage('SMS4B_MAIN_ERROR_ACTUAL_DATE_TWO');
                }

                //             15
                if ($startUp != '') {
                    $timeX = $timestampDateActual - 1800;
                    if ($timeX < $timestampStartUp) {
                        $dataFieldError = true;
                        $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
                        $arResult['RESULT_MESSAGE']['MESSAGE'][] = Loc::getMessage('SMS4B_MAIN_ERROR_ACTUAL_DATE_THREE');
                    }
                }

                //   14
                $timeX = $timestampDateActual - (86400 * 14);
                if ($timeX > $currTimeStamp) {
                    $dataFieldError = true;
                    $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
                    $arResult['RESULT_MESSAGE']['MESSAGE'][] = Loc::getMessage('SMS4B_MAIN_ERROR_ACTUAL_DATE_FOUR');
                }
            }

            //
            if ($requestData->getPost('ACTIVE_NIGHT_TIME_NS') !== 'Y' || ($requestData->getPost('DATE_FROM_NS') === null && $requestData->getPost('DATE_TO_NS') === null)) {
                $period = '';
            } else {
                $formedLeftPart = '';
                $formedRightPart = '';

                // -,
                $dateFromNS = $requestData->getPost('DATE_FROM_NS');
                $dateToNS = $requestData->getPost('DATE_TO_NS');
                $ordDateFromNS = ord($dateFromNS);
                $ordDateToNS = ord($dateToNS);

                if ($ordDateFromNS >= 65 && $ordDateFromNS <= 88 && $ordDateToNS >= 65 && $ordDateToNS <= 88) {
                    //    , ..  SMS-
                    //
                    if ($dateToNS == 'X') {
                        $formedLeftPart = 'A';
                    } else {
                        $formedLeftPart = chr($ordDateToNS + 1);
                    }
                    //
                    if ($dateFromNS == 'A') {
                        $formedRightPart = 'X';
                    } else {
                        $formedRightPart = chr($ordDateFromNS - 1);
                    }

                    $period = $formedRightPart . $formedLeftPart;
                } else {
                    $dataFieldError = true;
                    $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
                    $arResult['RESULT_MESSAGE']['MESSAGE'][] = Loc::getMessage('SMS4B_MAIN_ERROR_INTERVAL');
                }
            }


            $arPhones = $SMS4B->parse_numbers($requestData->getPost('destination_number'));
            if (!$dataFieldError) {
                foreach ($arPhones as $phone) {
                    $arPhonesMessages[$phone] = $requestData->getPost('message');
                }

                $arResult['RESULT_MESSAGE']['TYPE'] = 'OK';
                try {
                    $res = $SMS4B->SendSmsSaveGroup(
                        $arPhonesMessages,
                        htmlspecialchars($requestData->getPost('sender_number')),
                        $startUp,
                        $dateActual,
                        $period
                    );
                } catch (\Rarus\Sms4b\Sms4bException $e) {
                    $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
                    $arResult['RESULT_MESSAGE']['MESSAGE'][] = $e->getMessage();
                }

                $results_of_package_send['SEND'] = $results_of_package_send['NOT_SEND'] = 0;
                foreach ($res as $sms) {
                    if ($sms['Result'] > 0) {
                        ++$results_of_package_send['SEND'];
                    } else {
                        ++$results_of_package_send['NOT_SEND'];
                    }
                }
            }
        }
    }
} else {
    $arResult['RESULT_MESSAGE']['TYPE'] = 'ERROR';
    $arResult['RESULT_MESSAGE']['MESSAGE'] = $SMS4B->LastError . Loc::getMessage('SMS4B_MAIN_MOD_OPTIONS');
    $arResult['CAN_SEND'] = 'N';
}


if ($arResult['RESULT_MESSAGE']['TYPE'] == 'ERROR') {
    $strError = $arResult['RESULT_MESSAGE']['MESSAGE'];
    $dest = htmlspecialchars($requestData->getPost('destination_number'));
    $sender = htmlspecialchars($requestData->getPost('sender_number'));
    $mess = htmlspecialchars($requestData->getPost('message'));
}

require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/prolog_admin_after.php');
?>
<form name="form1" method="POST" action="<?= $GLOBALS['APPLICATION']->GetCurPage() ?>"
      onsubmit="form1.sub.disabled=true;">
    <script>
        var mess = new Array();
        mess['hours'] = "<?=Loc::getMessage('SMS4B_MAIN_HOURS')?>";
        mess['minutes'] = "<?=Loc::getMessage('SMS4B_MAIN_MINUTES')?>";
        mess['minutes-more'] = "<?=Loc::getMessage('SMS4B_MAIN_MINUTES_MORE')?>";
        mess['seconds'] = "<?=Loc::getMessage('SMS4B_MAIN_SECONDS')?>";
        mess['in-duration'] = "<?=Loc::getMessage('SMS4B_MAIN_IN_DURATION')?>";
        mess['with-interval'] = "<?=Loc::getMessage('SMS4B_MAIN_WITH_INTERVAL')?>";
        mess['no-balance'] = "<?=Loc::getMessage('SMS4B_MAIN_NO_BALANCE')?>";

        a = new Array();

        a["a"] = "<?=Loc::getMessage('SMS4B_MAIN_a')?>";
        a["A"] = "<?=Loc::getMessage('SMS4B_MAIN_A')?>";
        a["b"] = "<?=Loc::getMessage('SMS4B_MAIN_b')?>";
        a["B"] = "<?=Loc::getMessage('SMS4B_MAIN_B')?>";
        a["v"] = "<?=Loc::getMessage('SMS4B_MAIN_v')?>";
        a["V"] = "<?=Loc::getMessage('SMS4B_MAIN_V')?>";
        a["w"] = "<?=Loc::getMessage('SMS4B_MAIN_w')?>";
        a["W"] = "<?=Loc::getMessage('SMS4B_MAIN_W')?>";
        a["g"] = "<?=Loc::getMessage('SMS4B_MAIN_g')?>";
        a["G"] = "<?=Loc::getMessage('SMS4B_MAIN_G')?>";
        a["d"] = "<?=Loc::getMessage('SMS4B_MAIN_d')?>";
        a["D"] = "<?=Loc::getMessage('SMS4B_MAIN_D')?>";
        a["e"] = "<?=Loc::getMessage('SMS4B_MAIN_e')?>";
        a["E"] = "<?=Loc::getMessage('SMS4B_MAIN_E')?>";
        a["yo"] = "<?=Loc::getMessage('SMS4B_MAIN_yo')?>";
        a["YO"] = "<?=Loc::getMessage('SMS4B_MAIN_YO')?>";
        a["zh"] = "<?=Loc::getMessage('SMS4B_MAIN_zh')?>";
        a["Zh"] = "<?=Loc::getMessage('SMS4B_MAIN_Zh')?>";
        a["z"] = "<?=Loc::getMessage('SMS4B_MAIN_z')?>";
        a["Z"] = "<?=Loc::getMessage('SMS4B_MAIN_Z')?>";
        a["i"] = "<?=Loc::getMessage('SMS4B_MAIN_i')?>";
        a["I"] = "<?=Loc::getMessage('SMS4B_MAIN_I')?>";
        a["j"] = "<?=Loc::getMessage('SMS4B_MAIN_j')?>";
        a["k"] = "<?=Loc::getMessage('SMS4B_MAIN_k')?>";
        a["K"] = "<?=Loc::getMessage('SMS4B_MAIN_K')?>";
        a["l"] = "<?=Loc::getMessage('SMS4B_MAIN_l')?>";
        a["L"] = "<?=Loc::getMessage('SMS4B_MAIN_L')?>";
        a["m"] = "<?=Loc::getMessage('SMS4B_MAIN_m')?>";
        a["M"] = "<?=Loc::getMessage('SMS4B_MAIN_M')?>";
        a["n"] = "<?=Loc::getMessage('SMS4B_MAIN_n')?>";
        a["N"] = "<?=Loc::getMessage('SMS4B_MAIN_N')?>";
        a["o"] = "<?=Loc::getMessage('SMS4B_MAIN_o')?>";
        a["O"] = "<?=Loc::getMessage('SMS4B_MAIN_O')?>";
        a["p"] = "<?=Loc::getMessage('SMS4B_MAIN_p')?>";
        a["P"] = "<?=Loc::getMessage('SMS4B_MAIN_P')?>";
        a["r"] = "<?=Loc::getMessage('SMS4B_MAIN_r')?>";
        a["R"] = "<?=Loc::getMessage('SMS4B_MAIN_R')?>";
        a["s"] = "<?=Loc::getMessage('SMS4B_MAIN_s')?>";
        a["S"] = "<?=Loc::getMessage('SMS4B_MAIN_S')?>";
        a["t"] = "<?=Loc::getMessage('SMS4B_MAIN_t')?>";
        a["T"] = "<?=Loc::getMessage('SMS4B_MAIN_T')?>";
        a["u"] = "<?=Loc::getMessage('SMS4B_MAIN_u')?>";
        a["U"] = "<?=Loc::getMessage('SMS4B_MAIN_U')?>";
        a["f"] = "<?=Loc::getMessage('SMS4B_MAIN_f')?>";
        a["F"] = "<?=Loc::getMessage('SMS4B_MAIN_F')?>";
        a["x"] = "<?=Loc::getMessage('SMS4B_MAIN_x')?>";
        a["X"] = "<?=Loc::getMessage('SMS4B_MAIN_X')?>";
        a["c"] = "<?=Loc::getMessage('SMS4B_MAIN_c')?>";
        a["C"] = "<?=Loc::getMessage('SMS4B_MAIN_C')?>";
        a["ch"] = "<?=Loc::getMessage('SMS4B_MAIN_ch')?>";
        a["Ch"] = "<?=Loc::getMessage('SMS4B_MAIN_Ch')?>";
        a["sh"] = "<?=Loc::getMessage('SMS4B_MAIN_sh')?>";
        a["Sh"] = "<?=Loc::getMessage('SMS4B_MAIN_Sh')?>";
        a["shh"] = "<?=Loc::getMessage('SMS4B_MAIN_shh')?>";
        a["Shh"] = "<?=Loc::getMessage('SMS4B_MAIN_Shh')?>";
        a["y"] = "<?=Loc::getMessage('SMS4B_MAIN_y')?>";
        a["Y"] = "<?=Loc::getMessage('SMS4B_MAIN_Y')?>";
        a["yu"] = "<?=Loc::getMessage('SMS4B_MAIN_yu')?>";
        a["Yu"] = "<?=Loc::getMessage('SMS4B_MAIN_Yu')?>";
        a["ya"] = "<?=Loc::getMessage('SMS4B_MAIN_ya')?>";
        a["Ya"] = "<?=Loc::getMessage('SMS4B_MAIN_Ya')?>";

        a["mark1"] = "<?=Loc::getMessage('SMS4B_MAIN_MARK1')?>";
        a["mark2"] = "<?=Loc::getMessage('SMS4B_MAIN_MARK2')?>";
        a["mark3"] = "<?=Loc::getMessage('SMS4B_MAIN_MARK3')?>";
        a["mark4"] = "<?=Loc::getMessage('SMS4B_MAIN_MARK4')?>";

        a["<"] = "«";
        a[">"] = "»";
        a["-"] = "-";

        $(document).ready(function () {
            var params = {};
            params.summerTime = '<?=($arTime['tm_isdst'] > 0) ? 1 : 0?>';
            var obSendingForm = new SendingForm(params);
        })

    </script>

    <?= bitrix_sessid_post() ?>
    <input type="hidden" name="lang" value="<?= LANGUAGE_ID ?>">

    <?
    $aTabs = array(
        array(
            'DIV' => 'edit1',
            'TAB' => Loc::getMessage('SMS4B_MAIN_SEND_MESS'),
            'ICON' => 'sms4b_sendsms',
            'TITLE' => Loc::getMessage('SMS4B_MAIN_SEND_MESS')
        )
    );
    $tabControl = new CAdminTabControl('tabControl', $aTabs);
    ?>

    <?
    $tabControl->Begin();

    $tabControl->BeginNextTab();

    if (COption::GetOptionString('rarus.sms4b', 'sid') == '') {
        echo '<tr><td colspan="2">' . CAdminMessage::ShowMessage(Loc::getMessage('SMS4B_MAIN_CHECK_MODULE_OPT')) . '</td></tr>';

        $tabControl->Buttons();
        ?>
        <input type="submit" value="<?= Loc::getMessage('SMS4B_MAIN_REFRESH') ?>" name="refresh">
        <?
        $tabControl->End();

        return;
    }

    if ($arResult['RESULT_MESSAGE']['TYPE_DEF'] == 'CHANGING_DEF_SENDER_NUMBER') {
        echo '<tr><td><p>' . ShowNote($arResult['RESULT_MESSAGE']['MESSAGE_DEF']) . '</p></td></tr>';
    }

    if ($arResult['RESULT_MESSAGE']['TYPE'] == 'ERROR') {
        foreach ($arResult['RESULT_MESSAGE']['MESSAGE'] as $strError) {
            $errorMessages .= "$strError<br>";
        }

        $message = new CAdminMessage(array(
            'MESSAGE' => $errorMessages,
            'TYPE' => 'ERROR',
            'HTML' => true
        ));

        echo '<div class="adm-detail-content">' . $message->Show() . '</div>';
    }

    if ($arResult['RESULT_MESSAGE']['TYPE'] == 'OK') {
        if ($results_of_package_send['SEND'] == $numbersForSendCount) {
            $message = new CAdminMessage(array(
                'MESSAGE' => Loc::getMessage('SMS4B_MAIN_SMS4B_WAS_SEND') . $numbersForSendCount . '<br>' . Loc::getMessage('SMS4B_MAIN_SMS4B_LINK_OUT_LIST'),
                'TYPE' => 'OK',
                'HTML' => true
            ));
        } else {
            $message = new CAdminMessage(array(
                'MESSAGE' => Loc::getMessage('SMS4B_MAIN_SMS4B_NOT_SEND') . $results_of_package_send['SEND'] . Loc::getMessage('SMS4B_MAIN_SMS4B_SEPARATED')
                    . $numbersForSendCount . Loc::getMessage('SMS4B_MAIN_SMS4B_NUMBERS') . '<br>' . Loc::getMessage('SMS4B_MAIN_SMS4B_LINK_OUT_LIST'),
                'TYPE' => 'ERROR',
                'HTML' => true
            ));
        }
        echo '<div class="adm-detail-content">' . $message->Show() . '</div>';
    }

    global $USER;
    $rsUser_b = CUser::GetByID($USER->GetID());
    $arUser_b = $rsUser_b->Fetch();
    ?>

    <tr>
        <td class="left_td">
            <strong><?= Loc::getMessage('SMS4B_MAIN_NUMBER_SENDER') ?></strong><span class="orange">*</span>
        </td>
        <td>
            <select name="sender_number" id="senderNumber">
                <? foreach ($arResult['ADRESSES'] as $arIndex): ?>
                    <option
                        value="<?= htmlspecialchars($arIndex) ?>" <? if ($arIndex == COption::GetOptionString('rarus.sms4b',
                            'defsender') && $requestData->getPost('apply') !== null
                    ): ?> selected <? endif; ?>
                        <? if ($sender == $arIndex): ?> selected <? endif; ?>><?= $arIndex ?></option>
                <? endforeach; ?>
            </select>
            <input type="checkbox" class="check" name="def_sender" id="defSender" value="Y">&nbsp;<label
                for="defSender"><?= Loc::getMessage('SMS4B_MAIN_DEF_SEND') ?></label>
        </td>
    </tr>
    <tr>
        <td class="left_td">
            <strong><?= Loc::getMessage('SMS4B_MAIN_NUMBER_DESTINATION') ?></strong><span class="orange">*</span><br/>
        </td>
        <td>
            <div class="counters">
                <div id="correct-nums-div"><?= Loc::getMessage('SMS4B_MAIN_RECEIVERS') ?><span
                        id="correct-nums-tip"></span><span
                        id="correct-nums">0</span></div>
                <div id="need-sms-div"><?= Loc::getMessage('SMS4B_MAIN_NEED_SMS') ?><span id="need-sms-tip"></span><span
                        id="need-sms">0</span></div>
                <div id="countDoubled"><a href="javascript:void(0);"
                                          id="countDoubledLink"><?= Loc::getMessage('SMS4B_MAIN_KILL_DOUBLED_NUMBERS') ?></a>
                </div>
                <div class="clear"></div>
            </div>
            <textarea id="destinationNumber" name="destination_number"
                      <? if (!$requestData->getPost('destination_number')): ?>class="gray"<? endif; ?>><? if ($requestData->getPost('destination_number')): ?><?= implode("\n",
                    $destination) ?><? else: ?><?= Loc::getMessage('SMS4B_MAIN_DEST_COMMENT') ?><? endif; ?></textarea>
        </td>
    </tr>
    <tr>
        <td class="left_td">
            <strong><?= Loc::getMessage('SMS4B_MAIN_MESSAGE_TEXT') ?></strong><span class="orange">*</span>
            <div><a href="javascript:void(0);" id="caption"><?= Loc::getMessage('SMS4B_MAIN_CAPTION') ?></a></div>
        </td>
        <td>
            <div class="counters">
                <div id="lengmess-div"><?= Loc::getMessage('SMS4B_MAIN_TEXT_LENGTH') ?><span
                        id="lengmess-tip"></span><span
                        id="lengmess">0</span></div>
                <div id="size-part-div"><?= Loc::getMessage('SMS4B_MAIN_PART_SIZE') ?><span
                        id="size-part-tip"></span><span
                        id="size-part">160</span></div>
                <div id="parts-div"><?= Loc::getMessage('SMS4B_MAIN_PARTS') ?><span id="parts-tip"></span><span
                        id="parts">0</span>
                </div>
                <div class="clear"></div>
            </div>
            <textarea id="message" rows="7" name="message"
                      <? if (!$requestData->getPost('message')): ?>class="gray"<? endif; ?>><? if ($requestData->getPost('message')): ?><?= $requestData->getPost('message') ?><? else: ?><?= Loc::getMessage('SMS4B_MAIN_TEXT_COMMENT') ?><? endif; ?></textarea><br/>
            <div id="toLat-div">
                <?= Loc::getMessage('SMS4B_MAIN_TRANSLIT_TO') ?>
                <span id="toLat"><?= Loc::getMessage('SMS4B_MAIN_LATIN') ?></span>
                <?= Loc::getMessage('SMS4B_MAIN_OR') ?>
                <span id="toKir"><?= Loc::getMessage('SMS4B_MAIN_KIRIL') ?></span>
            </div>
            <div class="clear"></div>
        </td>
    </tr>
    <tr>
        <td class="left_td">
            <b class="time"><?= Loc::getMessage('SMS4B_MAIN_TIME_ZONE') ?></b>
        </td>
        <td>
            <?= $arResult['GMT_CONTROL'] ?>
        </td>
    </tr>
    <tr>
        <td class="left_td">
            <b><?= Loc::getMessage('SMS4B_MAIN_BEGIN_SEND_AT') ?></b>
        </td>
        <td>
            <input type="text" class="typeinput" id="BEGIN_SEND_AT" name="BEGIN_SEND_AT" size="20"
                   value="<?= gmdate('d.m.Y H:i', time() + (COption::GetOptionString('rarus.sms4b',
                               'gmt') * 3600)) ?>"/><? $GLOBALS['APPLICATION']->IncludeComponent('bitrix:main.calendar',
                '', array(
                    'SHOW_INPUT' => 'N',
                    'FORM_NAME' => 'form1',
                    'INPUT_NAME' => 'BEGIN_SEND_AT',
                    'INPUT_NAME_FINISH' => '',
                    'INPUT_VALUE' => '',
                    'INPUT_VALUE_FINISH' => '',
                    'SHOW_TIME' => 'Y',
                    'HIDE_TIMEBAR' => 'N'
                ), false); ?>
        </td>
    </tr>
    <tr>
        <td class="left_td">
            <input type="checkbox" id="ACTIVE_DATE_ACTUAL" name="ACTIVE_DATE_ACTUAL" value="Y"
                   onclick="activeNightTimeNsEvent('ACTIVE_DATE_ACTUAL','DATE_ACTUAL','');" <? if ($requestData->getPost('ACTIVE_DATE_ACTUAL') == 'Y'): ?> checked <? endif; ?> />
            <b><label for="ACTIVE_DATE_ACTUAL"
                      class="normal"><?= Loc::getMessage('SMS4B_MAIN_DATE_ACTUAL') ?></label></b>
        </td>
        <td>
            <input type="text" class="typeinput" id="DATE_ACTUAL" name="DATE_ACTUAL" size="20"
                   value="<?= gmdate('d.m.Y H:i',
                       time() + (COption::GetOptionString('rarus.sms4b', 'gmt') * 3600) + 86400) ?>"
                   disabled/><? $GLOBALS['APPLICATION']->IncludeComponent('bitrix:main.calendar',
                '', array(
                    'SHOW_INPUT' => 'N',
                    'FORM_NAME' => 'form1',
                    'INPUT_NAME' => 'DATE_ACTUAL',
                    'INPUT_NAME_FINISH' => '',
                    'INPUT_VALUE' => '',
                    'INPUT_VALUE_FINISH' => '',
                    'SHOW_TIME' => 'Y',
                    'HIDE_TIMEBAR' => 'N'
                ), false); ?>
        </td>
    </tr>
    <tr>
        <td class="left_td">
            <input type="checkbox" id="ACTIVE_NIGHT_TIME_NS" name="ACTIVE_NIGHT_TIME_NS" value="Y"
                   onclick="activeNightTimeNsEvent('ACTIVE_NIGHT_TIME_NS','DATE_FROM_NS','DATE_TO_NS');"
                <? if ($requestData->getPost('ACTIVE_NIGHT_TIME_NS') == 'Y'): ?> checked <? endif; ?> />
            <b><label for="ACTIVE_NIGHT_TIME_NS"
                      class="normal"><?= Loc::getMessage('SMS4B_MAIN_NIGHT_TIME_NS') ?></label></b>
        </td>
        <td>
            <select id="DATE_FROM_NS"
                    name="DATE_FROM_NS" <? if ($requestData->getPost('ACTIVE_NIGHT_TIME_NS') != 'Y'): ?> disabled <? endif; ?>>
                <? $checked_symbol_date_from_ns = chr(87); ?>
                <? for ($i = 0; $i < 24; $i++): ?>
                    <option
                        value="<?= chr(65 + $i) ?>" <? if (chr(65 + $i) == $checked_symbol_date_from_ns): ?> selected <? endif; ?>>
                        <?= $i ?>:00
                    </option>
                <? endfor; ?>
            </select> <?= Loc::getMessage('SMS4B_MAIN_TO') ?>
            <select id="DATE_TO_NS" name="DATE_TO_NS">
                <? $checked_symbol_date_to_ns = chr(73); ?>
                <? for ($i = 0; $i < 24; $i++): ?>
                    <option
                        value="<?= chr(65 + $i) ?>" <? if (chr(65 + $i) == $checked_symbol_date_to_ns): ?> selected <? endif; ?>>
                        <?= $i ?>:59
                    </option>
                <? endfor; ?>
            </select>
        </td>
    </tr>

    <tr>
        <td class="left_td">
            <label for="uniformSending" class="normal"> <b><?= Loc::getMessage('SMS4B_MAIN_UNIFORM') ?></b><span
                    class="required"><sup>1</sup></span></label>
        </td>
        <td>
            <input type="checkbox" id="uniformSending" name="uniformSending" value="Y"
                   <? if ($requestData->getPost('uniformSending')): ?>checked<? endif; ?>/>
        </td>
    </tr>

    <tr>
        <td colspan="2">
            <div class="adm-info-message">
                <span class="required"><sup>1</sup></span><?= Loc::getMessage('SMS4B_MAIN_SMS4B_UNIFORM_DESC') ?><br>
            </div>
        </td>
    </tr>
    <?

    $tabControl->Buttons();
    ?>
    <input type="submit" value="<?= Loc::getMessage('SMS4B_MAIN_SUBMIT') ?>" name="apply">
    <?
    $tabControl->End();
    ?>
</form>

<?
require_once($_SERVER['DOCUMENT_ROOT'] . '/bitrix/modules/main/include/epilog_admin.php');
?>
