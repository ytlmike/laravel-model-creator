<?php

namespace ModelCreator\Util;

use Illuminate\Console\Command;
use ModelCreator\Util\FieldTypes\BaseFieldType;

class ModelField
{
    protected $command;

    protected $name;

    protected $type;

    protected $length;

    protected $nullable = false;

    protected $defaultValue = null;

    protected $comment = '';

    protected $isFirstField = false;

    protected $fieldType;

    public function __construct(Command $command, $isFirstField = false)
    {
        $this->command = $command;
        $this->isFirstField = $isFirstField;
    }

    public function setName()
    {
        $question = $this->isFirstField
            ? 'New field name (press <return> to stop adding fields):'
            : 'Add another property? Enter the property name (or press <return> to stop adding fields):';
        $name = $this->command->ask($question);
        if (empty($name)) {
            return false;
        }
        $this->name = $name;
        return $this;
    }

    public function setType()
    {
        $type = $this->command->choice('Field type:', array_keys($this->getFieldTypeMap()));
        $typeMap = $this->getFieldTypeMap();
        if (isset($typeMap[$type])) {
            $this->type = $type;
            $this->fieldType = $typeMap[$type];
        }
        return $this;
    }

    public function setLength()
    {
        $length = $this->command->ask('Field display length:');
        $length = (is_numeric($length) && $length > 0) ? $length : $this->fieldType['default_length'];
        $this->length = $length;
        return $this;
    }

    public function setNullable()
    {
        $this->nullable =  $this->command->confirm('Can this field be null in the database (nullable):');
        return $this;
    }

    public function setDefaultValue()
    {
        $defaultValue = $this->command->ask('Default value of this field in tht database:');
        $defaultValue = empty($defaultValue) ? $this->fieldType['default_value'] : $defaultValue;
        $this->defaultValue = $defaultValue;
        return $this;
    }

    public function setComment()
    {
        $comment = $this->command->ask('Comment of this field in tht database:');
        if (!empty($comment)) {
            $this->comment = $comment;
        }
        return $this;
    }

    /**
     * get all of the possible field types
     *
     * @return array[]
     */
    public function getFieldTypeMap()
    {
        return [
            'int' => [
                'main_type' => 'integer',
                'default_length' => 11,
                'default_value' => 0
            ],
            'tinyint' => [
                'main_type' => 'integer',
                'default_length' => 1,
                'default_value' => 0
            ],
            'varchar' => [
                'main_type' => 'string',
                'default_length' => 255,
                'default_value' => ''
            ],
            'datetime' => [
                'main_type' => 'date/time',
                'default_length' => 0,
                'default_value' => '0001-01-01 00:00:00'
            ],
        ];
    }
}
