<?php


namespace ModelCreator\Printers;


use ModelCreator\Nodes\Stmt\EmptyLine;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard;
use PhpParser\Node\Stmt;

class PrettyPrinter extends Standard
{
    public function prettyPrintFile(array $stmts) : string
    {
        foreach ($stmts as $k=> $item) {
            if ($item instanceof Stmt\Class_) {
                $this->prettyClass($stmts[$k]);
            }
            if ($item instanceof Stmt\Namespace_) {
                foreach ($item->stmts as $k2 => $stmt) {
                    if ($stmt instanceof Stmt\Class_) {
                        $this->prettyClass($stmts[$k]->stmts[$k2]);
                    }
                }
            }
        }

        return parent::prettyPrintFile($stmts);
    }

    protected function prettyClass(&$class)
    {
        $i=0;
        while ($i<count($class->stmts)) {
            $item2 = $class->stmts[$i];
            if ($item2 instanceof EmptyLine) {
                continue;
            }
            $next = $i+1;
            if ($next<count($class->stmts) && !$class->stmts[$next] instanceof EmptyLine) {
                array_splice($class->stmts, $next, 0, [new EmptyLine()]);
                $i++;
            }
            $i++;
        }
    }

    protected function p(Node $node, $parentFormatPreserved = false): string
    {
        return parent::p($node, $parentFormatPreserved);
    }

    protected function pStmt_EmptyLine()
    {
        return '';
    }
}
