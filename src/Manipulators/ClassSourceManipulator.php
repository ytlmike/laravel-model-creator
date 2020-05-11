<?php


namespace ModelCreator\Manipulators;

use Composer\Autoload\ClassLoader;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ModelCreator\Exceptions\ClassSourceManipulatorException;
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
use ReflectionException;

class ClassSourceManipulator
{
    private $fullClassName;
    private $parser;
    private $oldTokens;
    private $oldStmts;
    private $newStmts;

    /**
     * ClassSourceManipulator constructor.
     *
     * @param string $fullClassName
     * @throws ClassSourceManipulatorException
     * @throws ReflectionException
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

    /**
     * get new ast code string
     * @return string
     */
    public function printCode()
    {
        return (new PrettyPrinter())->printFormatPreserving($this->newStmts, $this->oldStmts, $this->oldTokens);
    }

    /**
     * check & complete the file path and write new code to class file
     *
     * @throws ClassSourceManipulatorException
     * @throws ReflectionException
     */
    public function writeCode()
    {
        $filename = $this->getSourceFileName();
        if (!file_exists($filename)) {
            $structure = explode(DIRECTORY_SEPARATOR, $filename);
            array_pop($structure);
            $dirname = implode(DIRECTORY_SEPARATOR, $structure);
            if (!file_exists($dirname)) (new Filesystem())->makeDirectory($dirname, 0755, true);
        }
        file_put_contents($filename, $this->printCode());
    }

    /**
     * initial the class structure when the stmts is empty
     *
     * @return $this
     * @throws ReflectionException
     */
    public function initClass()
    {
        $this->getClassNode();
        return $this;
    }

    /**
     * add class use node
     *
     * @param $use
     * @throws ReflectionException
     */
    public function addUseNode($use)
    {
        $namespaceNode = $this->getNamespaceNode();
        $targetNode = $this->getLastChildNode($namespaceNode, function ($node) {
            return $node instanceof Node\Stmt\Use_;
        });
        $index = $targetNode ? $this->getChileNodeIndex($namespaceNode, $targetNode) : 0;
        $builder = new Builder\Use_($use, Node\Stmt\Use_::TYPE_NORMAL);
        $newNodes = $targetNode ? [$builder->getNode()] : [$builder->getNode(), new EmptyLine()];
        array_splice($namespaceNode->stmts, $index, 0, $newNodes);
    }

    /**
     * add class const node
     *
     * @param $constName
     * @param $constValue
     * @param string|string[] $comments
     * @return $this
     * @throws ReflectionException
     */
    public function addClassConst($constName, $constValue, $comments = [])
    {
        if (is_string($comments)) {
            $comments = [$comments];
        }
        if ($this->getClassConstNode($constName)) {
            return $this;
        }
        $builder = new ClassConst($constName, $constValue);
        if (is_array($comments) && !empty($comments)) {
            $builder->setDocComment($this->createDocCommentStr($comments));
        }
        $this->appendClassChildNode($builder->getNode());
        return $this;
    }

    /**
     * add class property node
     *
     * @param string $propertyName
     * @param null $defaultValue
     * @param int $modifier
     * @param string|string[] $comments
     * @return $this
     * @throws ReflectionException
     */
    public function addProperty(string $propertyName, $defaultValue = null, $modifier = Node\Stmt\Class_::MODIFIER_PRIVATE, $comments = [])
    {
        if (is_string($comments)) {
            $comments = [$comments];
        }
        if ($this->getPropertyNode($propertyName)) {
            return $this;
        }
        $propertyBuilder = new Property($propertyName);
        if ($defaultValue !== null) {
            $propertyBuilder->setDefault($defaultValue);
        }
        $this->setBuilderModifier($propertyBuilder, $modifier);
        if (is_array($comments) && !empty($comments)) {
            $propertyBuilder->setDocComment($this->createDocCommentStr($comments));
        }
        $this->appendClassChildNode($propertyBuilder->getNode());
        return $this;
    }

    /**
     * add class property getter method node
     *
     * @param $propertyName
     * @return $this|bool|false
     * @throws ReflectionException
     */
    public function addGetter($propertyName)
    {
        $methodName = 'get' . Str::studly($propertyName);
        $propertyFetchExpr = new Node\Expr\PropertyFetch(new Node\Expr\Variable('this'), $propertyName);
        $stmts =new Node\Stmt\Return_($propertyFetchExpr);
        return $this->addClassMethod($methodName, $propertyName, $stmts);
    }

    /**
     * add class property setter method node
     *
     * @param $propertyName
     * @return $this|bool|false
     * @throws ReflectionException
     */
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
     * add class method node
     *
     * @param $methodName
     * @param string|string[] $params
     * @param Node\Stmt| Node\Stmt[] $expressions
     * @param int $modifier
     * @param string|string[] $comments
     * @return $this|bool|false
     * @throws ReflectionException
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
        $this->appendClassChildNode($builder->getNode());
        return $this;
    }

    /**
     * get source code of current class
     *
     * @return false|string|void
     * @throws ClassSourceManipulatorException
     * @throws ReflectionException
     */
    protected function getSourceCode()
    {
        $filename = $this->getSourceFileName();
        return file_exists($filename) ? file_get_contents($filename) : '';
    }

    /**
     * get file name of current class
     *
     * @return false|string|void
     * @throws ClassSourceManipulatorException
     * @throws ReflectionException
     */
    protected function getSourceFileName()
    {
        if (class_exists($this->fullClassName)) {
            return (new \ReflectionClass($this->fullClassName))->getFileName();
        } else {
            return $this->resolveNotExistClassFileName();
        }
    }

    /**
     * class auto loader cannot find the class file just created in this process, try to resolve with namespace prefix.
     *
     * @return string
     * @throws ClassSourceManipulatorException
     */
    protected function resolveNotExistClassFileName()
    {
        $fullClassName = ltrim($this->fullClassName, '\\');
        $structure = explode('\\', $fullClassName);
        $className = array_pop($structure);
        if (count($structure) < 2) {
            return base_path($fullClassName . '.php');
        }
        /** @var ClassLoader $classLoader */
        $classLoader = require base_path('vendor/autoload.php');
        $psr4Prefixes = $classLoader->getPrefixesPsr4();
        $nameSpace = '';
        while (!empty($structure)) {
            $item = array_shift($structure);
            $nameSpace = (empty($nameSpace) ? $item : $nameSpace . "\\$item") . '\\';
            if (isset($psr4Prefixes[$nameSpace])) {
                return $psr4Prefixes[$nameSpace][0] . DIRECTORY_SEPARATOR . implode(DIRECTORY_SEPARATOR, $structure) . DIRECTORY_SEPARATOR . $className . '.php';
            }
        }
        throw new ClassSourceManipulatorException('cannot resolve the filename of class: ' . $this->fullClassName);
    }

    /**
     * get namespace of current class
     *
     * @return string
     * @throws ReflectionException
     */
    protected function getNamespace()
    {
        if (class_exists($this->fullClassName)) {
            return (new \ReflectionClass($this->fullClassName))->getNamespaceName();
        } else {
            $fullClassName = ltrim($this->fullClassName, '\\');
            $structure = explode('\\', $fullClassName);
            array_pop($structure);
            return implode('\\', $structure);
        }
    }

    /**
     * get the modifying class name
     *
     * @return mixed|string
     * @throws ReflectionException
     */
    protected function getClassName()
    {
        if (class_exists($this->fullClassName)) {
            return (new \ReflectionClass($this->fullClassName))->getName();
        } else {
            $fullClassName = ltrim($this->fullClassName, '\\');
            $structure = explode('\\', $fullClassName);
            return array_pop($structure);
        }
    }

    /**
     * add node to class node stmts (only [class const / class property / class method] nodes)
     *
     * @param Node $newNode
     * @throws ReflectionException
     */
    protected function appendClassChildNode(Node $newNode)
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
            $nextNode = $classNode->stmts[$index + 1] ?? null;
            if ($nextNode && $nextNode instanceof EmptyLine) {
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

    /**
     * search for the class const node with given name in class node stmts
     *
     * @param string $constName
     * @return bool|Node
     * @throws ReflectionException
     */
    protected function getClassConstNode(string $constName)
    {
        return $this->getFirstChildNode($this->getClassNode(), function ($node) use ($constName) {
            return $node instanceof Node\Stmt\ClassConst && $node->consts[0]->name->toString() == $constName;
        });
    }

    /**
     * search for the class property node with given name in class node stmts
     *
     * @param string $propertyName
     * @return bool|Node
     * @throws ReflectionException
     */
    protected function getPropertyNode(string $propertyName)
    {
        return $this->getFirstChildNode($this->getClassNode(), function ($node) use ($propertyName) {
            return $node instanceof Node\Stmt\Property && $node->props[0]->name->toString() == $propertyName;
        });
    }

    /**
     * search for the class method node with given name in class node stmts
     *
     * @param string $methodName
     * @return bool|Node
     * @throws ReflectionException
     */
    protected function getClassMethodNode(string $methodName)
    {
        return $this->getFirstChildNode($this->getClassNode(), function ($node) use ($methodName) {
            return $node instanceof Node\Stmt\ClassMethod && $node->name->toString() == $methodName;
        });
    }

    /**
     * @return bool|Node\Stmt\Class_
     * @throws ReflectionException
     */
    protected function getClassNode()
    {
        $namespaceNode = $this->getNamespaceNode();
        $classNode = $this->getFirstChildNode($namespaceNode, function ($node) {
            return $node instanceof Node\Stmt\Class_;
        });
        if (!$classNode) {
            $this->addClassNode();
        }
        return $classNode;
    }

    /**
     * add a class node to the namespace node stmts.
     *
     * @throws ReflectionException
     */
    protected function addClassNode()
    {
        $namespaceNode = $this->getNamespaceNode();
        $classNode = (new Builder\Class_($this->getClassName()))->getNode();
        array_push($namespaceNode->stmts, $classNode);
    }

    /**
     * get the namespace node of the stmts, generate if not exists.
     *
     * @return Node\Stmt\Namespace_
     * @throws ReflectionException
     */
    protected function getNamespaceNode()
    {
        if (empty($this->newStmts)) {
            $this->addNamespaceNode();
        }
        return $this->newStmts[0];
    }

    /**
     * @throws ReflectionException
     */
    protected function addNamespaceNode()
    {
        $builder = new Builder\Namespace_($this->getNamespace());
        array_unshift($this->newStmts, $builder->getNode());
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
     * @return Node|bool|false
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
     *
     * @param Node $parentNode
     * @param Node $childNode
     * @return false|int|string
     */
    protected function getChileNodeIndex(Node $parentNode, Node $childNode)
    {
        return array_search($childNode, $parentNode->stmts);
    }

    /**
     * @param string[] $comments
     * @return string
     */
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

    /**
     * @param Builder $builder
     * @param $modifier
     */
    protected function setBuilderModifier(Builder $builder, $modifier)
    {
        if (!method_exists($builder, 'makePublic')) {
            return;
        }
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
