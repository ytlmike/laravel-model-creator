<?php


namespace ModelCreator\Util;

use ModelCreator\Exceptions\ModelCreatorException;
use PhpParser\Builder\Property;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class ClassSourceManipulator
{
    private $fullClassName;
    private $parser;
    private $ast;

    /**
     * ClassSourceManipulator constructor.
     * @param string $fullClassName
     * @throws \ReflectionException
     */
    public function __construct(string $fullClassName)
    {
        $this->fullClassName = $fullClassName;
        $this->parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $this->ast = $this->parser->parse($this->getSourceCode());
    }

    public function print()
    {
        $code = (new Standard())->prettyPrint($this->ast[0]->stmts);
        echo $code;
    }

    /**
     * @param string $propertyName
     * @param null $defaultValue
     * @param int $modifier
     * @param array $comments
     * @throws ModelCreatorException
     */
    public function addProperty(string $propertyName, $defaultValue = null, $modifier = Node\Stmt\Class_::MODIFIER_PRIVATE, $comments = [])
    {
        if ($this->getPropertyNode($propertyName)) {
            return;
        }
        $propertyBuilder = new Property($propertyName);
        if ($defaultValue !== null) {
            $propertyBuilder->setDefault($defaultValue);
        }
        switch ($modifier) {
            case Node\Stmt\Class_::MODIFIER_PRIVATE:
                $propertyBuilder->makePrivate();
                break;
            case Node\Stmt\Class_::MODIFIER_PUBLIC:
                $propertyBuilder->makePublic();
                break;
            default:
                $propertyBuilder->makeProtected();
        }
        $this->appendProperty($propertyBuilder->getNode());
    }

    /**
     * @return false|string
     * @throws \ReflectionException
     */
    private function getSourceCode()
    {
        $fileName = (new \ReflectionClass($this->fullClassName))->getFileName();
        return file_get_contents($fileName);
    }

    /**
     * @return bool|Node
     * @throws ModelCreatorException
     */
    private function getClassNode()
    {
        $classNode = $this->getFirstChildNode($this->ast[0], function ($node) {
            return $node instanceof Node\Stmt\Class_;
        });
        if (!$classNode) {
            throw new ModelCreatorException('can not find class node');
        }
        return $classNode;
    }

    private function appendClassConst(Node $constNode)
    {
        $classNode = $this->getClassNode();
        $lastConstNode = $this->getLastChildNode($classNode, function ($node) {
            return $node instanceof Node\Stmt\ClassConst;
        });
        if (!$lastConstNode) {
            $lastConstNode = $this->getLastChildNode($classNode, function ($node) {
                return $node instanceof Node\Stmt\Property;
            });
        }
        if (!$lastConstNode) {
            array_unshift($classNode->stmts, $constNode);
            return;
        }
        $index = $this->getChileNodeIndex($classNode, $lastConstNode);
        array_splice($classNode->stmts, $index + 1, 0, $constNode);
    }

    private function appendProperty(Node $propertyNode)
    {
        $classNode = $this->getClassNode();
        $lastPropertyNode = $this->getLastChildNode($classNode, function ($node) {
            return $node instanceof Node\Stmt\Property;
        });
        if (!$lastPropertyNode) {
            $lastPropertyNode = $this->getLastChildNode($classNode, function ($node) {
                return $node instanceof Node\Stmt\ClassConst;
            });
        }
        if (!$lastPropertyNode) {
            array_unshift($classNode->stmts, [$propertyNode]);
            return;
        }
        $index = $this->getChileNodeIndex($classNode, $lastPropertyNode);
        array_splice($classNode->stmts, $index + 1, 0, [$propertyNode]);
    }

    /**
     * @param string $propertyName
     * @return bool|Node
     * @throws ModelCreatorException
     */
    private function getPropertyNode(string $propertyName)
    {
        return $this->getFirstChildNode($this->getClassNode(), function ($node) use ($propertyName) {
            return $node instanceof Node\Stmt\Property && $node->props[0]->name->toString() == $propertyName;
        });
    }

    /**
     * @param Node $parentNode
     * @param callable $filter
     * @return Node | bool
     */
    private function getFirstChildNode(Node $parentNode, callable $filter)
    {
        foreach ($parentNode->stmts as $node) {
            if ($filter($node)) {
                return $node;
            }
        }
        return  false;
    }

    /**
     * @param Node $parentNode
     * @param callable $filter
     * @return Node | false
     */
    private function getLastChildNode(Node $parentNode, callable $filter)
    {
        $targetNode = false;
        foreach ($parentNode->stmts as $node) {
            if ($filter($node)) {
                $targetNode = $node;
            }
        }
        return  $targetNode;
    }

    /**
     * get the child node index in parent node
     * @param Node $parentNode
     * @param Node $childNode
     * @return false|int|string
     */
    private function getChileNodeIndex(Node $parentNode, Node $childNode)
    {
        return array_search($childNode, $parentNode->stmts);
    }
}
