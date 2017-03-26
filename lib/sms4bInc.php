<?php

/**
 * @author AZAREV
 * @version 1.3.0
 */

namespace Rarus\Sms4b;

use Bitrix\Main\Entity;

/**
 * Class Sms4bIncTable
 * @package Rarus\Sms4b
 */
class Sms4bIncTable extends Entity\DataManager
{
    /**
     * Возвращает название таблицы
     *
     * @return string - название таблицы
     */
    public static function getTableName()
    {
        return 'b_sms4b_incoming';
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
            new Entity\DatetimeField('MOMENT', array(
                'column_name' => 'Moment'
            )),
            new Entity\DatetimeField('TIMEOFF', array(
                'column_name' => 'TimeOff'
            )),
            new Entity\StringField('SOURCE', array(
                'column_name' => 'Source'
            )),
            new Entity\StringField('DESTINATION', array(
                'column_name' => 'Destination'
            )),
            new Entity\IntegerField('CODING', array(
                'column_name' => 'Coding'
            )),
            new Entity\TextField('BODY', array(
                'column_name' => 'Body'
            )),
            new Entity\IntegerField('TOTAL', array(
                'column_name' => 'Total'
            )),
            new Entity\IntegerField('PART', array(
                'column_name' => 'Part'
            ))
        );
    }
}