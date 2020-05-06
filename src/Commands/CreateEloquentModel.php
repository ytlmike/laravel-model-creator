<?php

namespace ModelCreator\Commands;

use Illuminate\Console\Command;
use ModelCreator\Util\ModelField;

class CreateEloquentModel extends Command
{
    protected $signature = 'create:model {name?}';

    protected $description = 'Create a eloquent model class with defining fields and get-set methods';

    protected $modelName;

    protected $fields = [];

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
    protected function askModelName()
    {
        $name = $this->argument('name');
        if (empty($name)) {
            $name = $this->ask('Class name of the model to create:');
        }
        if (empty($name)) {
            $this->askModelName();
        }
        $this->modelName = $name;
        return $this;
    }

    /**
     * ask fields data
     *
     * @return $this
     */
    protected function askFields()
    {
        while (true) {
            $currentField = new ModelField($this);
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
        }
        return $this;
    }

    /**
     * create the model class file
     */
    protected function generate()
    {
        //TODO
    }

    protected function addField(ModelField $field)
    {
        $this->fields[] = $field;
        return $this;
    }
}