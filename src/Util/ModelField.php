<?php

namespace ytlmike\ModelCreator\Util;

use Illuminate\Console\Command;

class ModelField
{
//    const FIELD_TYPE_INTEGER = 'integer';
//    const FIELD_TYPE_VARCHAR = 'varchar';
//    const FIELD_TYPE_FLOAT = 'float';
//    const FIELD_TYPE_TEXT = 'text';
//    const FIELD_TYPE_DATE = 'date';
//    const FIELD_TYPE_ = 'datetime';

    protected $command;

    protected $name;

    protected $type;

    protected $length;

    protected $nullable = false;

    protected $defaultValue = null;

    protected $comment = '';

    protected $isFirstField = false;

    protected $fieldTypes = [];

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
        $type = $this->command->choice('Field type:', $this->fieldTypes);
        $this->type = $type;
        return $this;
    }

    public function setLength()
    {
        $length = $this->command->ask('Field length:');
        if (is_numeric($length) && $length > 0) {
            $this->length = $length;
        } else {
            // TODO
        }
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
        if (!empty($defaultValue)) {
            $this->defaultValue = $defaultValue;
        }
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
}
