<?php

namespace ModelCreator\Field;

class VarcharField extends FieldType
{
    protected $name = "varchar";
    protected $mainType = ModelField::FIELD_TYPE_STRING;
    protected $defaultLen = 32;
    protected $defaultVal = '';
    protected $migrateMethod = 'string';
}
