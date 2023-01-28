<?php

namespace ModelCreator\Field;

class FieldType
{
    protected $name;
    protected $mainType;
    protected $defaultLen;
    protected $defaultVal;
    protected $migrateMethod;

    public function getName(): string
    {
        return $this->name;
    }

    public function getMainType(): string
    {
        return $this->mainType;
    }

    public function getDefaultLength(): int
    {
        return $this->defaultLen;
    }

    public function getDefaultValue()
    {
        return $this->defaultVal;
    }

    public function getMigrateMethod()
    {
        return $this->migrateMethod;
    }
}
