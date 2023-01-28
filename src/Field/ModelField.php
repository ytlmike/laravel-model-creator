<?php

namespace ModelCreator\Field;

use Illuminate\Console\Command;
use PhpParser\BuilderFactory;
use PhpParser\Node;

class ModelField
{
    const FIELD_TYPE_INTEGER = 'integer';
    const FIELD_TYPE_STRING = 'string';
    const FIELD_TYPE_DATETIME = 'date/time';

    const INDEX_NONE = 'none';
    const INDEX_NORMAL = 'normal';
    const INDEX_UNIQUE = "unique";

    protected $primaryKey = false;

    protected $index = self::INDEX_NONE;

    protected $command;

    protected $name;

    protected $type;

    protected $length;

    protected $nullable = false;

    protected $defaultValue = null;

    protected $comment = '';

    protected $isFirstField = false;

    /**
     * @var FieldType $fieldType
     */
    protected $fieldType;

    public function __construct(Command $command, $isFirstField = false)
    {
        $this->command = $command;
        $this->isFirstField = $isFirstField;
    }

    public function setPrimaryKey()
    {
        $this->primaryKey = true;
    }

    public function isPrimaryKey(): bool
    {
        return $this->primaryKey;
    }

    public function setIndex(string $type)
    {
        $this->index = $type;
    }

    public function getIndex(): string
    {
        return $this->index;
    }

    public function askIndex()
    {
        $indexType = $this->command->choice('Field index:', [self::INDEX_NONE, self::INDEX_NORMAL, self::INDEX_UNIQUE]);
        $this->setIndex($indexType);
    }

    public function askName()
    {
        $question = $this->isFirstField
            ? 'New field name (press <return> to stop adding fields)'
            : 'Add another property? Enter the property name (or press <return> to stop adding fields)';
        $name = $this->command->ask($question);
        if (empty($name)) {
            return false;
        }
        $this->setName($name);
        return $this;
    }

    public function setName($name)
    {
        $this->name = $name;
    }

    public function getName()
    {
        return $this->name;
    }

    public function askType()
    {
        $type = $this->command->choice('Field type:', array_keys($this->getFieldTypeMap()));
        $typeMap = $this->getFieldTypeMap();
        if (isset($typeMap[$type])) {
            $this->type = $type;
            $this->fieldType = $typeMap[$type];
        }
    }

    public function setType($type)
    {
        $this->type = $type;
    }

    public function getType()
    {
        return $this->type;
    }

    public function askLength()
    {
        $length = $this->command->ask('Field display length', $this->fieldType->getDefaultLength());
        $length = (is_numeric($length) && $length > 0) ? $length : $this->fieldType->getDefaultLength();
        $this->setLength($length);
    }

    public function setLength($length)
    {
        $this->length = $length;
    }

    public function getLength()
    {
        return $this->length;
    }

    public function askNullable()
    {
        $this->setNullable($this->command->confirm('Can this field be null in the database (nullable)'));
    }

    public function setNullable($nullable)
    {
        $this->nullable = $nullable;
    }

    public function getNullable()
    {
        return $this->nullable;
    }

    public function askDefaultValue()
    {
        $defaultValue = $this->command->ask('Default value of this field in tht database', $this->fieldType->getDefaultValue());
        $defaultValue = empty($defaultValue) ? $this->fieldType->getDefaultValue() : $defaultValue;
        $this->setDefaultValue($defaultValue);
    }

    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;
    }

    public function getDefaultValue()
    {
        return $this->defaultValue;
    }

    public function askComment()
    {
        $comment = $this->command->ask('Comment of this field in the database', '');
        if (!empty($comment)) {
            $this->setComment($comment);
        }
    }

    public function setComment($comment)
    {
        $this->comment = $comment;
    }

    public function getComment()
    {
        return $this->comment;
    }

    public function getFieldType(): FieldType
    {
        return $this->fieldType;
    }

    public function makeFieldComment(ModelField $field): array
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
     * get all the possible field types
     *
     * @return FieldType[]
     */
    private function getFieldTypeMap(): array
    {
        /** @var FieldType[] $availableFieldTypes */
        $availableFieldTypes = [
            new IntField(),
            new VarcharField(),
            new DateTimeField(),
            new TinyintField(),
        ];
        $map = [];
        foreach ($availableFieldTypes as $fieldType) {
            $map[$fieldType->getName()] = $fieldType;
        }
        return $map;
    }
}
