<?php


namespace ModelCreator\Util;


use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;

class PrettyPrinter extends Standard
{
    protected function p(Node $node, $parentFormatPreserved = false): string
    {
        return parent::p($node, $parentFormatPreserved);
    }

    protected function pStmt_EmptyLine()
    {
        return '';
    }
}