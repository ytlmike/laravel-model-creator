<?php

namespace ModelCreator\NodeBuilders;

use PhpParser\Builder;
use PhpParser\Builder\Param;
use PhpParser\Builder\Use_;
use PhpParser\BuilderHelpers;
use PhpParser\Node;

class Closure implements Builder
{
    protected $params = [];
    protected $stmts = [];
    protected $uses = [];

    /** @var Node\AttributeGroup[] */
    protected $attributeGroups = [];

    public function addParam(string $name, string $type = '')
    {
        $builder = new Param($name);
        if (!empty($type)) {
            $builder->setType( $type);
        }
        $this->params[] = $builder->getNode();
    }

    public function addUse(string $name)
    {
        $builder = new Use_($name, Node\Stmt\Use_::TYPE_NORMAL);
        $this->uses[] = $builder->getNode();
    }

    public function addStmt(Node $stmt)
    {
        $this->stmts[] = BuilderHelpers::normalizeStmt($stmt);
    }

    public function getNode(): Node
    {
        return new Node\Expr\Closure([
            'params' => $this->params,
            'uses' => $this->uses,
            'stmts' => $this->stmts,
        ]);
    }
}
