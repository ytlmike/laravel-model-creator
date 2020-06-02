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
        $this->manipulator = new ModelSourceManipulator($modelName);
    }

    /**
     * @throws ClassSourceManipulatorException
     * @throws ReflectionException
     */
    public function init()
    {
        $classExists = class_exists($this->getModelFullClassName());
        $this->manipulator->initClass()->writeCode();
        $this->command->info($classExists ? "Class {$this->getModelFullClassName()} already exists." : "Class {$this->getModelFullClassName()} created successfully.");
    }

    /**
     * @param ModelField $field
     * @throws ClassSourceManipulatorException
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
        $this->manipulator->writeCode();
    }

    /**
     * @return string
     */
    private function getModelFullClassName()
    {
        return $this->nameSpace . $this->modelName;
    }
}