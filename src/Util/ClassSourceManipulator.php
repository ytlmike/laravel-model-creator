<?php


namespace ModelCreator\Util;

use ModelCreator\Exceptions\ModelCreatorException;
use ModelCreator\Util\Node\Stmt\EmptyLine;
use PhpParser\Builder\Property;
use PhpParser\Lexer\Emulative;
use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\CloningVisitor;
use PhpParser\Parser\Php7;

class ClassSourceManipulator
{
    private $fullClassName;
    private $parser;
    private $oldTokens;
    private $oldStmts;
    private $newStmts;

    /**
     * ClassSourceManipulator constructor.
     * @param string $fullClassName
     * @throws \ReflectionException
     */
    public function __construct(string $fullClassName)
    {
        $this->fullClassName = $fullClassName;
        $lexer = new Emulative([
            'usedAttributes' => [
                'comments',
                'startLine', 'endLine',
                'startTokenPos', 'endTokenPos',
            ],
        ]);
        $this->parser = new Php7($lexer);
        $this->newStmts = $this->parser->parse($this->getSourceCode());

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new CloningVisitor());

        $this->oldTokens = $lexer->getTokens();
        $this->oldStmts = $this->parser->parse($this->getSourceCode());
        $this->newStmts = $traverser->traverse($this->oldStmts);
    }

    public function printCode()
    {
        return (new PrettyPrinter())->printFormatPreserving($this->newStmts, $this->oldStmts, $this->oldTokens);
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
        if (is_string($comments)) {
            $comments = [$comments];
        }
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
        if (is_array($comments) && !empty($comments)) {
            $propertyBuilder->setDocComment($this->createDocCommentStr($comments));
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
        $classNode = $this->getFirstChildNode($this->newStmts[0], function ($node) {
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
        $needAppend = [(new EmptyLine()), $propertyNode];
        $classNode = $this->getClassNode();

        // try to find last property node.
        $targetNode = $this->getLastChildNode($classNode, function ($node) {
            return $node instanceof Node\Stmt\Property;
        });

        // if there is no properties, try to find a class const node.
        if (!$targetNode) {
            $targetNode = $this->getLastChildNode($classNode, function ($node) {
                return $node instanceof Node\Stmt\ClassConst;
            });
        }

        // if there is neither properties nor class constants, insert at top of the class without a new line.
        if (!$targetNode) {
            array_unshift($classNode->stmts, [$propertyNode]);
            return;
        }

        $index = $this->getChileNodeIndex($classNode, $targetNode);
        array_splice($classNode->stmts, $index + 1, 0, $needAppend);
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

    private function createDocCommentStr(array $comments)
    {
        $firstLine = "/**\n";
        $lastLine = ' */';
        $body = '';
        foreach ($comments as $comment) {
            $body .= " * $comment\n";
        }
        return $firstLine . $body . $lastLine;
    }
}
