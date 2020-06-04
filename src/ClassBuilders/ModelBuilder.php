<?php

namespace ModelCreator\ClassBuilders;

use Illuminate\Console\Command;
use ModelCreator\Exceptions\ClassSourceManipulatorException;
use ModelCreator\Manipulators\ModelSourceManipulator;
use ModelCreator\ModelField;
use ReflectionException;

class ModelBuilder implements ClassBuilderInterface
{
    const OPTION_NAME_USE_CONST = 'const';

    protected $command;

    protected $useConst;

    protected $manipulator;

    protected $modelName;

    protected $nameSpace = '\\App\\Models\\';

    /**
     * @var ModelField[]
     */
    protected $fields;

    /**
     * ModelBuilder constructor.
     * @param Command $command
     * @param $modelName
     * @throws ClassSourceManipulatorException
     * @throws ReflectionException
     */
    public function __construct($modelName, Command $command)
    {
        $this->command = $command;
        $options = $command->options();
        $this->useConst = $options[self::OPTION_NAME_USE_CONST] ?? false;
        $this->modelName = $modelName;
        $this->manipulator = new ModelSourceManipulator($this->getModelFullClassName());
    }

    /**
     * @throws ReflectionException
     */
    public function init()
    {
        if (class_exists($this->getModelFullClassName())) {
            $this->command->info("Class {$this->getModelFullClassName()} already exists.");
        }
        $this->manipulator->initClass();
    }

    /**
     * @param ModelField $field
     * @return bool
     * @throws ReflectionException
     */
    public function fieldValid(ModelField $field)
    {
        $valid = true;
        $exist = $this->manipulator->fieldExist($field->getName());
        if (!$exist) {
            $valid = false;
            $this->command->info("Field {$field->getName()} already exists.");
        }
        // TODO: check if the filename is legal
        return $valid;
    }

    /**
     * @param ModelField $field
     * @throws ReflectionException
     */
    public function addField(ModelField $field)
    {
        if ($this->useConst) {
            $this->manipulator
                ->addFieldConst($field)
                ->addFieldGetterWithConst($field)
                ->addFieldSetterWithConst($field);
        } else {
            $this->manipulator
                ->addGetter($field->getName())
                ->addSetter($field->getName());
        }
    }

    /**
     * @return string
     */
    private function getModelFullClassName()
    {
        return $this->nameSpace . $this->modelName;
    }

    /**
     * @throws ClassSourceManipulatorException
     * @throws ReflectionException
     */
    public function __destruct()
    {
        $this->manipulator->writeCode();
        $this->command->info("Class {$this->getModelFullClassName()} created successfully.");
    }
}
