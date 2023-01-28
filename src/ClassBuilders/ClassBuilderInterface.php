<?php

namespace ModelCreator\ClassBuilders;

use ModelCreator\Field\ModelField;

interface ClassBuilderInterface
{
    public function setTableName(string $name): void;

    public function existsField(ModelField $field): bool;

    public function addField(ModelField $field): void;
}
