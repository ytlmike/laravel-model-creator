<?php

namespace ModelCreator\Manipulators;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ModelCreator\ModelField;
use PhpParser\Node;
use ReflectionException;

class ModelSourceManipulator extends ClassSourceManipulator
{
    protected $fieldConstPrefix = 'FIELD_';

    /**
     * generate getter method of the field
     *
     * @param ModelField $field
     * @return $this
     * @throws ReflectionException
     */
    public function addFieldGetterWithConst(ModelField $field)
    {
        $fieldName = $field->getName();
        $constName = $this->makeFieldConstName($fieldName);
        $constFetch = new Node\Expr\ConstFetch(new Node\Name("self::{$constName}"));
        $getAttribute = new Node\Expr\MethodCall(new Node\Expr\Variable('this'), 'getAttribute', [new Node\Arg($constFetch)]);
        $this->addClassMethod($this->getFieldGetterMethodName($fieldName), [], new Node\Stmt\Return_($getAttribute)); // return $this->getAttribute(self::FIELD_XXX);
        return $this;
    }

    /**
     * generate setter method of the field
     *
     * @param ModelField $field
     * @return $this
     * @throws ReflectionException
     */
    public function addFieldSetterWithConst(ModelField $field)
    {
        $fieldName = $field->getName();
        $constName = $this->makeFieldConstName($fieldName);
        $constFetch = new Node\Expr\ConstFetch(new Node\Name("self::{$constName}"));
        $args = [new Node\Arg($constFetch), new Node\Arg(new Node\Expr\Variable($fieldName))];
        $expressions = [
            new Node\Expr\MethodCall(new Node\Expr\Variable('this'), 'setAttribute', $args),    // $this->setAttribute(self::FIELD_XXX, $xxx);
            new Node\Stmt\Return_(new Node\Expr\Variable('this'))                                   // return $this;
        ];
        $this->addClassMethod($this->getFieldSetterMethodName($fieldName), $fieldName, $expressions);
        return $this;
    }

    /**
     * generate class const (if not exists) of the field
     *
     * @param ModelField $field
     * @return ModelSourceManipulator
     * @throws ReflectionException
     */
    public function addFieldConst(ModelField $field)
    {
        $fieldName = $field->getName();
        $constName = $this->makeFieldConstName($fieldName);
        return  $this->addClassConst($constName, $fieldName, $field->makeFieldComment($field));
    }

    /**
     * @param $fieldName
     * @return bool
     * @throws ReflectionException
     */
    public function fieldExist($fieldName)
    {
        return $this->getClassMethodNode($this->getFieldGetterMethodName($fieldName)) instanceof Node\Stmt\ClassMethod;
    }

    protected function makeFieldConstName($fieldName)
    {
        return $this->fieldConstPrefix . strtoupper($fieldName);
    }

    protected function addClassNode()
    {
        parent::addClassNode();
        $classNode = $this->getClassNode();
        $classNode->extends = new Node\Name('Model');
    }

    protected function addNamespaceNode()
    {
        parent::addNamespaceNode();
        $this->addUseNode(Model::class);
    }

    protected function getFieldGetterMethodName($fieldName)
    {
        return 'get' . Str::studly($fieldName);
    }

    protected function getFieldSetterMethodName($fieldName)
    {
        return 'set' . Str::studly($fieldName);
    }
}
