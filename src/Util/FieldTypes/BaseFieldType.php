<?php

namespace ModelCreator\Util\FieldTypes;

abstract class baseFieldType
{
    protected $mainType;

    protected $subType;

    protected $defaultLength;

    protected $defaultValue;

    public function getMainType()
    {
        return $this->mainType;
    }

    public function getSubType()
    {
        return $this->subType;
    }

    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    public function getDefaultLength()
    {
        return $this->defaultLength;
    }
}