<?php

namespace ModelCreator\Field;

class DateTimeField extends FieldType
{
    protected $name = "datetime";
    protected $mainType = ModelField::FIELD_TYPE_DATETIME;
    protected $defaultLen = 0;
    protected $defaultVal = '0001-01-01 00:00:00';
    protected $migrateMethod = 'dateTime';
}
