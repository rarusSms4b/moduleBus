<?php

/**
 * @author AZAREV
 * @version 1.3.0
 */

namespace Rarus\Sms4b;

use Bitrix\Main\Entity;

/**
 * Class Sms4bTable
 * @package Rarus\Sms4b
 */
class Sms4bTable extends Entity\DataManager
{
    /**
     * Возвращает название таблицы
     *
     * @return string - название таблицы
     */
    public static function getTableName()
    {
        return 'b_sms4b';
    }

    /**
     * Описание структуры сущности
     *
     * @return array - массив объектов полей
     */
    public static function getMap()
    {
        return array(
            new Entity\IntegerField('ID', array(
                'primary' => true,
                'autocomplete' => true,
                'column_name' => 'id'
            )),
            new Entity\StringField('GUID', array(
                'required' => true
            )),
            new Entity\StringField('SENDERNAME', array(
                'column_name' => 'SenderName'
            )),
            new Entity\StringField('DESTINATION', array(
                'column_name' => 'Destination'
            )),
            new Entity\DatetimeField('STARTSEND', array(
                'column_name' => 'StartSend'
            )),
            new Entity\DatetimeField('LASTMODIFIED', array(
                'column_name' => 'LastModified'
            )),
            new Entity\IntegerField('STATUS', array(
                'column_name' => 'Status'
            )),
            new Entity\IntegerField('COUNTPART', array(
                'column_name' => 'CountPart'
            )),
            new Entity\IntegerField('SENDPART', array(
                'column_name' => 'SendPart'
            )),
            new Entity\IntegerField('CODETYPE', array(
                'column_name' => 'CodeType'
            )),
            new Entity\TextField('TEXT', array(
                'column_name' => 'TextMessage'
            )),
            new Entity\IntegerField('ORDER_ID', array(
                'column_name' => 'Sale_Order'
            )),
            new Entity\IntegerField('POSTING', array(
                'column_name' => 'Posting'
            )),
            new Entity\StringField('EVENT_NAME', array(
                'column_name' => 'Events'
            )),
            new Entity\StringField('RESULT', array(
                'column_name' => 'Result'
            ))
        );
    }
}