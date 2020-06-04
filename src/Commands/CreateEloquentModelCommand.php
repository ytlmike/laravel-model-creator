<?php

namespace ModelCreator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ModelCreator\ClassBuilders\ClassBuilderInterface;
use ModelCreator\ClassBuilders\ModelBuilder;
use ModelCreator\Exceptions\ClassSourceManipulatorException;
use ModelCreator\ModelField;
use ReflectionException;

class CreateEloquentModelCommand extends Command
{
    protected $signature = 'create:model {name?} {--const} {--with-migration}';

    protected $description = 'Create a eloquent model class with defining fields and getter/setter methods';

    protected $modelName;

    /**
     * @var ClassBuilderInterface[]
     */
    protected $builders;

    public function handle()
    {
        $this->askModelName()->initBuilders()->askFields();
    }

    public function initBuilders()
    {
        $this->builders[] = new ModelBuilder($this->modelName, $this);
        // TODO: add other builders
        foreach ($this->builders as $builder) {
            $builder->init();
        }
        return $this;
    }

    /**
     * ask for the model name
     *
     * @return $this
     * @throws ClassSourceManipulatorException
     * @throws ReflectionException
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
        $match = preg_match('/^[a-zA-Z_\x80-\xff][a-zA-Z0-9_\x80-\xff]*$/', Str::studly($name));
        if (!$match) {
            $this->comment("invalid class name, please input a class name begin with a letter or underscore.\n");
        }
        return $match;
    }

    private function askFields()
    {
        /** @var ModelBuilder $modelBuilder */
        $modelBuilder = $this->builders[0];
        $isFirst = true;
        while (true) {
            $currentField = new ModelField($this, $isFirst);
            $available = $currentField->setName();

            if (!$available) {
                break;
            }

            if ($modelBuilder->fieldValid($currentField)) {
                continue;
            }

            $currentField
                ->setType()
                ->setLength()
                ->setNullable()
                ->setDefaultValue()
                ->setIndex()
                ->setComment();
            foreach ($this->builders as $builder) {
                $builder->addField($currentField);
            }
            $isFirst = false;
        }
        return $this;
    }
}
