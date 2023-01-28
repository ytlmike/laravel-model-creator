<?php

namespace ModelCreator\Commands;

use ModelCreator\ClassBuilders\ClassBuilderInterface;
use ModelCreator\ClassBuilders\MigrationBuilder;
use ModelCreator\ClassBuilders\ModelBuilder;
use ModelCreator\Exceptions\ClassSourceManipulatorException;
use ModelCreator\Field\FieldType;
use ModelCreator\Field\ModelField;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use ReflectionException;

class CreateEloquentModelCommand extends Command
{
    protected $signature = 'create:model {name?} {--const} {--with-migration}';

    protected $description = 'Create a eloquent model class with defining fields and getter/setter methods';

    /**
     * @var ClassBuilderInterface[]
     */
    protected $builders;

    protected $arr;

    protected $test;

    /**
     * @throws ReflectionException
     * @throws ClassSourceManipulatorException
     */
    public function handle()
    {
        $this->builders = [
            new ModelBuilder($this),
            new MigrationBuilder($this)
        ];
        $this->askModelName()->askFields();
    }

    /**
     * ask for the model name
     * @throws ClassSourceManipulatorException
     * @throws ReflectionException
     */
    private function askModelName(): CreateEloquentModelCommand
    {
        $name = $this->argument('name');
        if (empty($name)) {
            $name = $this->ask('Class name of the model to create');
        }
        if (empty($name) || !$this->checkModelName($name)) {
            return $this->askModelName();
        }
        foreach ($this->builders as $builder) {
            $builder->setTableName($name);
        }
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

    private function askFields(): void
    {
        $id = new ModelField($this);
        $id->setName('id');
        $id->setType('int');
        $id->setNullable(false);
        $id->setLength(11);
        $id->setPrimaryKey();

        $isFirst = true;
        while (true) {
            $currentField = new ModelField($this, $isFirst);
            if (!$currentField->askName()) {
                break;
            }
            $currentField->askType();
            $currentField->askLength();
            $currentField->askNullable();
            $currentField->askDefaultValue();
            $currentField->askIndex();
            $currentField->askComment();

            foreach ($this->builders as $builder) {
                if (!$builder->existsField($currentField)) { //TODO
                    $builder->addField($currentField);
                }
            }

            $isFirst = false;
        }
    }
}
