<?php

namespace ModelCreator\Commands;

use Illuminate\Console\Command;
use ModelCreator\Util\ModelField;

class CreateEloquentModel extends Command
{
    protected $signature = 'create:model {name?}';

    protected $description = 'Create a eloquent model class with defining fields and get-set methods';

    private $modelName;

    private $fields = [];

    public function handle()
    {
        $this->askModelName()
            ->askFields()
            ->generate();
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
            $this->askModelName();
        }
        $this->modelName = $name;
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

            $currentField->setType()
                ->setLength()
                ->setNullable()
                ->setDefaultValue()
                ->setComment();

            $this->addField($currentField);
            $isFirst = false;
        }
        return $this;
    }

    /**
     * create the model class file
     */
    private function generate()
    {
        //TODO
    }

    private function addField(ModelField $field)
    {
        $this->fields[] = $field;
        return $this;
    }
}