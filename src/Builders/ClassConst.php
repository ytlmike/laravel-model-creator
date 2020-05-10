<?php


namespace ModelCreator\Builders;


use PhpParser\Builder;
use PhpParser\BuilderHelpers;
use PhpParser\Node;

class ClassConst implements Builder
{
    protected $name;

    protected $value;

    protected $attributes = [];

    public function __construct($name, $value)
    {
        $this->name = $name;
        $this->value = $value;
    }

    public function setDocComment($docComment) {
        $this->attributes = [
            'comments' => [BuilderHelpers::normalizeDocComment($docComment)]
        ];

        return $this;
    }

    public function getNode(): Node
    {
        $consts = [new Node\Const_($this->name, new Node\Scalar\String_($this->value))];
        return new Node\Stmt\ClassConst($consts, 0,  $this->attributes);
    }
}
