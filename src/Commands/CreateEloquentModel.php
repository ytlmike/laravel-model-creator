<?php

namespace ModelCreator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use ModelCreator\Manipulators\ClassSourceManipulator;
use ModelCreator\Manipulators\ModelSourceManipulator;
use ModelCreator\ModelField;

class CreateEloquentModel extends Command
{
    protected $signature = 'create:model {name?} {--const}';

    protected $description = 'Create a eloquent model class with defining fields and getter/setter methods';

    private $modelName;

    private $fields = [];

    private $nameSpace = '\\App\\Models\\';

    public function handle()
    {
        $this->askModelName()
            ->askFields();
    }

    /**
     * ask the model name
     *
     * @return $this
     */
    private function askModelName()
    {
        $name = $this->argument('name');
        if (empty($name)) {
            $name = $this->ask('Class name of the model to create');
        }
        if (empty($name) || !$this->checkModelName($name)) {
            return $this->askModelName();
        }
        $this->modelName = Str::studly($name);
        $this->generateClass();
        return $this;
    }

    /**
     * check if the model name is valid
     *
     * @param $name
     * @return false|int
     */
    private function checkModelName($name)
    {
        $match = preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', $name);
        if (!$match) {
            $this->comment("invalid class name, please input a class name begin with a letter or underscore.\n");
        }
        return $match;
    }

    /**
     * ask fields data
     *
     * @return $this
     */
    private function askFields()
    {
        $isFirst = true;
        while (true) {
            $currentField = new ModelField($this, $isFirst);
            $available = $currentField->setName();

            if (!$available){
                break;
            }

            $currentField
                ->setType()
                ->setLength()
                ->setNullable()
                ->setDefaultValue()
                ->setComment();

            if ($this->option('const')) {
                $this->addFieldWithConst($currentField);
            } else {
                $this->addField($currentField);
            }
            $isFirst = false;
        }
        return $this;
    }

    /**
     * create the model class file
     */
    private function generateClass()
    {
        $classExists = class_exists($this->getModelFullClassName());
        Artisan::call("make:model Models/{$this->modelName}");
        $this->info($classExists ? "Class {$this->getModelFullClassName()} already exists." : "Class {$this->getModelFullClassName()} created successfully.");
    }

    private function getModelFullClassName()
    {
        return $this->nameSpace . $this->modelName;
    }

    private function addField(ModelField $field)
    {
        $this->fields[] = $field;
        $manipulator = new ClassSourceManipulator($this->getModelFullClassName());
        $manipulator->addGetter($field->getName())->addSetter($field->getName())->writeCode();
        return $this;
    }

    private function addFieldWithConst(ModelField $field)
    {
        $this->fields[] = $field;
        $manipulator = new ModelSourceManipulator($this->getModelFullClassName());
        $manipulator
            ->addFieldGetterWithConst($field)
            ->addFieldSetterWithConst($field)
            ->writeCode();
        return $this;
    }
}
