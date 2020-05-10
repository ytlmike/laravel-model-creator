<?php


namespace ModelCreator\Manipulators;

use Illuminate\Support\Str;
use ModelCreator\Exceptions\ModelCreatorException;
use ModelCreator\Builders\ClassConst;
use ModelCreator\Nodes\Stmt\EmptyLine;
use ModelCreator\Printers\PrettyPrinter;
use PhpParser\Builder;
use PhpParser\Builder\Method;
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

    public function writeCode()
    {
        file_put_contents($this->getSourceFile(), $this->printCode());
    }

    public function addClassConst($constName, $constValue, $comments = [])
    {
        if (is_string($comments)) {
            $comments = [$comments];
        }
        if ($this->getClassConstNode($constName)) {
            return;
        }
        $builder = new ClassConst($constName, $constValue);
        if (is_array($comments) && !empty($comments)) {
            $builder->setDocComment($this->createDocCommentStr($comments));
        }
        $this->appendNode($builder->getNode());
        return $this;
    }

    /**
     * @param string $propertyName
     * @param null $defaultValue
     * @param int $modifier
     * @param array $comments
     * @return $this
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
        $this->setBuilderModifier($propertyBuilder, $modifier);
        if (is_array($comments) && !empty($comments)) {
            $propertyBuilder->setDocComment($this->createDocCommentStr($comments));
        }
        $this->appendNode($propertyBuilder->getNode());
        return $this;
    }

    /**
     * @param $propertyName
     * @throws ModelCreatorException
     */
    public function addGetter($propertyName)
    {
        $methodName = 'get' . Str::studly($propertyName);
        $propertyFetchExpr = new Node\Expr\PropertyFetch(new Node\Expr\Variable('this'), $propertyName);
        $stmts =new Node\Stmt\Return_($propertyFetchExpr);
        return $this->addClassMethod($methodName, $propertyName, $stmts);
    }

    public function addSetter($propertyName)
    {
        $methodName = 'set' . Str::studly($propertyName);
        $stmts = [
            new Node\Stmt\Expression(new Node\Expr\Assign(
                new Node\Expr\PropertyFetch(new Node\Expr\Variable('this'), $propertyName),
                new Node\Expr\Variable($propertyName)
            )),
            new Node\Stmt\Return_(new Node\Expr\Variable('this')),
        ];
        return $this->addClassMethod($methodName, $propertyName, $stmts);
    }

    /**
     * @param $methodName
     * @param array $params array of (string) params
     * @param array $expressions  array of (PhpParser\Nodes\Stmt) expressions
     * @param int $modifier
     * @param array $comments
     * @return self | false
     * @throws ModelCreatorException
     */
    public function addClassMethod($methodName, $params = [], $expressions = [], $modifier = Node\Stmt\Class_::MODIFIER_PUBLIC, $comments = [])
    {
        if ($this->getClassMethodNode($methodName)) {
            return false;
        }
        if (is_string($comments)) {
            $comments = [$comments];
        }
        if (is_string($params)) {
            $params = [$params];
        }
        if ($expressions instanceof Node\Stmt) {
            $expressions = [$expressions];
        }
        $builder = new Method($methodName);
        if (is_array($comments) && !empty($comments)) {
            $builder->setDocComment($this->createDocCommentStr($comments));
        }
        $this->setBuilderModifier($builder, $modifier);
        if (is_array($params)) {
            foreach ($params as $param) {
                $builder->addParam(new Builder\Param($param));
            }
        }
        $builder->addStmts($expressions);
        $this->appendNode($builder->getNode());
        return $this;
    }

    /**
     * @return false|string
     * @throws \ReflectionException
     */
    private function getSourceCode()
    {
        return file_get_contents($this->getSourceFile());
    }

    private function getSourceFile()
    {
        return (new \ReflectionClass($this->fullClassName))->getFileName();
    }

    /**
     * @return bool|Node
     * @throws ModelCreatorException
     */
    protected function getClassNode()
    {
        $classNode = $this->getFirstChildNode($this->newStmts[0], function ($node) {
            return $node instanceof Node\Stmt\Class_;
        });
        if (!$classNode) {
            throw new ModelCreatorException('can not find class node');
        }
        return $classNode;
    }

    /**
     * @param Node $newNode
     * @throws ModelCreatorException
     */
    protected function appendNode(Node $newNode)
    {
        $classNode = $this->getClassNode();
        $targetNode = null;

        if ($newNode instanceof Node\Stmt\ClassMethod) {
            $targetNode = $this->getLastChildNode($classNode, function ($node) {
                return $node instanceof Node\Stmt\ClassMethod;
            });
        }

        if (!$targetNode && ($newNode instanceof Node\Stmt\ClassMethod || $newNode instanceof Node\Stmt\Property)) {
            $targetNode = $this->getLastChildNode($classNode, function ($node) {
                return $node instanceof Node\Stmt\Property;
            });
        }

        if (!$targetNode) {
            $targetNode = $this->getLastChildNode($classNode, function ($node) {
                return $node instanceof Node\Stmt\ClassConst;
            });
        }

        if ($targetNode) {
            $index = $this->getChileNodeIndex($classNode, $targetNode);
            $preNode = $classNode->stmts[$index + 1];
            if ($preNode instanceof EmptyLine) {
                $insertNodes = [$newNode];
                $index = $index + 2;
            } else {
                $insertNodes = [(new EmptyLine()), $newNode];
                $index = $index + 1;
            }
            array_splice($classNode->stmts, $index, 0, $insertNodes);
        } else {
            array_unshift($classNode->stmts, new EmptyLine());
            array_unshift($classNode->stmts, $newNode);
        }
    }

    protected function getClassConstNode(string $constName)
    {
        return $this->getFirstChildNode($this->getClassNode(), function ($node) use ($constName) {
            return $node instanceof Node\Stmt\ClassConst && $node->consts[0]->name->toString() == $constName;
        });
    }

    /**
     * @param string $propertyName
     * @return bool|Node
     * @throws ModelCreatorException
     */
    protected function getPropertyNode(string $propertyName)
    {
        return $this->getFirstChildNode($this->getClassNode(), function ($node) use ($propertyName) {
            return $node instanceof Node\Stmt\Property && $node->props[0]->name->toString() == $propertyName;
        });
    }

    protected function getClassMethodNode(string $methodName)
    {
        return $this->getFirstChildNode($this->getClassNode(), function ($node) use ($methodName) {
            return $node instanceof Node\Stmt\ClassMethod && $node->name->toString() == $methodName;
        });
    }

    /**
     * @param Node $parentNode
     * @param callable $filter
     * @return Node | bool
     */
    protected function getFirstChildNode(Node $parentNode, callable $filter)
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
    protected function getLastChildNode(Node $parentNode, callable $filter)
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
    protected function getChileNodeIndex(Node $parentNode, Node $childNode)
    {
        return array_search($childNode, $parentNode->stmts);
    }

    protected function createDocCommentStr(array $comments)
    {
        $firstLine = "/**\n";
        $lastLine = ' */';
        $body = '';
        foreach ($comments as $comment) {
            $body .= " * $comment\n";
        }
        return $firstLine . $body . $lastLine;
    }

    protected function setBuilderModifier(Builder $builder, $modifier)
    {
        if ($builder instanceof Method || $builder instanceof Property) {
            switch ($modifier) {
                case Node\Stmt\Class_::MODIFIER_PRIVATE:
                    $builder->makePrivate();
                    break;
                case Node\Stmt\Class_::MODIFIER_PUBLIC:
                    $builder->makePublic();
                    break;
                default:
                    $builder->makeProtected();
            }
        }
    }
}
