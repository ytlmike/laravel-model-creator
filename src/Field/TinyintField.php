<?php

namespace ModelCreator\Field;

class TinyintField extends FieldType
{
    protected $name = "tinyint";
    protected $mainType = ModelField::FIELD_TYPE_INTEGER;
    protected $defaultLen = 1;
    protected $defaultVal = 0;
    protected $migrateMethod = 'tinyInteger';
}
