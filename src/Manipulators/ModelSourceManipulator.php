<?php


namespace ModelCreator\Manipulators;


use Illuminate\Support\Str;
use ModelCreator\ModelField;
use PhpParser\Node;

class ModelSourceManipulator extends ClassSourceManipulator
{
    protected $fieldConstPrefix = 'FIELD_';

    public function addFieldGetterWithConst(ModelField $field)
    {
        $fieldName = $field->getName();
        $constName = $this->makeFieldConstName($fieldName);
        $this->addClassConst($constName, $fieldName, $this->makeFieldComment($field));
        $methodName = 'get' . Str::studly($fieldName);
        $constFetch = new Node\Expr\ConstFetch(new Node\Name("self::{$constName}"));
        $getAttribute = new Node\Expr\MethodCall(new Node\Expr\Variable('this'), 'getAttribute', [new Node\Arg($constFetch)]);
        $this->addClassMethod($methodName, [], new Node\Stmt\Return_($getAttribute));
        return $this;
    }

    public function addFieldSetterWithConst(ModelField $field)
    {
        $fieldName = $field->getName();
        $constName = $this->makeFieldConstName($fieldName);
        $this->addClassConst($constName, $fieldName, $this->makeFieldComment($field));
        $methodName = 'set' . Str::studly($fieldName);
        $constFetch = new Node\Expr\ConstFetch(new Node\Name("self::{$constName}"));
        $args = [new Node\Arg($constFetch), new Node\Arg(new Node\Expr\Variable($fieldName))];
        $expressions = [
            new Node\Expr\MethodCall(new Node\Expr\Variable('this'), 'setAttribute', $args),
            new Node\Stmt\Return_(new Node\Expr\Variable('this'))
        ];
        $this->addClassMethod($methodName, $fieldName, $expressions);
        return $this;
    }

    protected function makeFieldComment(ModelField $field)
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

    protected function makeFieldConstName($fieldName)
    {
        return $this->fieldConstPrefix . strtoupper($fieldName);
    }
}
