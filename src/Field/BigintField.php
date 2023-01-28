<?php

namespace ModelCreator\Field;

class BigintField extends FieldType
{
    protected $name = "bigint";
    protected $mainType = ModelField::FIELD_TYPE_INTEGER;
    protected $defaultLen = 20;
    protected $defaultVal = 0;
    protected $migrateMethod = 'bigInteger';
}
