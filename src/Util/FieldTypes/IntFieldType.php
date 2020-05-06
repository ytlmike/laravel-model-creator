<?php

namespace ModelCreator\Util\FieldTypes;

class IntFieldType extends BaseFieldType
{
    protected $mainType = 'integer';

    protected $subType = 'int';

    protected $defaultLength = 11;

    protected $defaultValue = 0;
}