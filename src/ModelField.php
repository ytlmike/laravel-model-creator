<?php

namespace ModelCreator;

use Illuminate\Console\Command;

class ModelField
{
    const INDEX_NONE = 'none';

    const INDEX_NORMAL = 'normal';

    const INDEX_UNIQUE = 'unique';

    protected $command;

    protected $name;

    protected $type;

    protected $length;

    protected $nullable = false;

    protected $defaultValue = null;

    protected $comment = '';

    protected $isFirstField = false;

    protected $fieldType;

    protected $index;

    public function __construct(Command $command, $isFirstField = false)
    {
        $this->command = $command;
        $this->isFirstField = $isFirstField;
    }

    public function setName()
    {
        $question = $this->isFirstField
            ? 'New field name (press <return> to stop adding fields)'
            : 'Add another property? Enter the property name (or press <return> to stop adding fields)';
        $name = $this->command->ask($question);
        if (empty($name)) {
            return false;
        }
        $this->name = $name;
        return $this;
    }

    public function getName()
    {
        return $this->name;
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

    public function getType()
    {
        return $this->type;
    }

    public function setLength()
    {
        $length = $this->command->ask('Field display length', $this->fieldType['default_length']);
        $length = (is_numeric($length) && $length > 0) ? $length : $this->fieldType['default_length'];
        $this->length = $length;
        return $this;
    }

    public function getLength()
    {
        return $this->length;
    }

    public function setNullable()
    {
        $this->nullable = $this->command->confirm('Can this field be null in the database (nullable)');
        return $this;
    }

    public function getNullable()
    {
        return $this->nullable;
    }

    public function setDefaultValue()
    {
        $defaultValue = $this->command->ask('Default value of this field in tht database', $this->fieldType['default_value']);
        $defaultValue = empty($defaultValue) ? $this->fieldType['default_value'] : $defaultValue;
        $this->defaultValue = $defaultValue;
        return $this;
    }

    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    public function setIndex()
    {
        $index = $this->command->choice('Create a index on this field?', [
            self::INDEX_NONE, self::INDEX_NORMAL, self::INDEX_UNIQUE,
        ]);
        $this->index = $index;
        return $this;
    }

    public function getIndex()
    {
        return $this->index;
    }

    public function setComment()
    {
        $comment = $this->command->ask('Comment of this field in the database', '');
        if (!empty($comment)) {
            $this->comment = $comment;
        }
        return $this;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function makeFieldComment(ModelField $field)
    {
        $comments = [];
        if (!empty($field->getComment())) {
            $comments[] = $field->getComment();
        }
        $fieldAttrs = "type='{$field->getType()}'";
        if (!empty($field->getLength())) {
            $fieldAttrs .= ", length={$field->getLength()}";
        }
        if (!empty($field->getDefaultValue())) {
            $fieldAttrs .= ", default='{$field->getDefaultValue()}'";
        }
        $fieldAttrs .= ', ' . ($field->getNullable() ? 'null' : 'not null');
        $comments[] = "@Column ($fieldAttrs)";
        return $comments;
    }

    /**
     * get all of the possible field types
     *
     * @return array[]
     */
    private function getFieldTypeMap()
    {
        return [
            'int' => [
                'default_length' => 11,
                'default_value' => 0,
            ],
            'bigint' => [
                'default_length' => 20,
                'default_value' => 0,
            ],
            'tinyint' => [
                'default_length' => 1,
                'default_value' => 0,
            ],
            'varchar' => [
                'default_length' => 255,
                'default_value' => '',
            ],
            'datetime' => [
                'default_length' => 0,
                'default_value' => '0001-01-01 00:00:00',
            ],
        ];
    }
}
