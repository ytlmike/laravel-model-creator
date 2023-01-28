<?php

namespace ModelCreator\Field;

class IntField extends FieldType
{
    protected $name = "int";
    protected $mainType = ModelField::FIELD_TYPE_INTEGER;
    protected $defaultLen = 11;
    protected $defaultVal = 0;
    protected $migrateMethod = 'integer';
}
