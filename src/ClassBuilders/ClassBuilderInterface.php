<?php


namespace ModelCreator\ClassBuilders;


use ModelCreator\ModelField;

interface ClassBuilderInterface
{
    public function init();

    public function addField(ModelField $field);
}