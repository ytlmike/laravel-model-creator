<?php


namespace ModelCreator\Util\Node\Stmt;


use PhpParser\Node\Stmt;

class EmptyLine extends Stmt
{

    public function getType(): string
    {
        return 'Stmt_EmptyLine';
    }

    public function getSubNodeNames(): array
    {
        return [];
    }
}