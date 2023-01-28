<?php

namespace ModelCreator\ClassBuilders;

use ModelCreator\Printers\PrettyPrinter;
use Illuminate\Support\Str;
use PhpParser\Builder;
use PhpParser\Builder\Method;
use PhpParser\Builder\Property;
use PhpParser\BuilderFactory;
use PhpParser\Node;
use PhpParser\Node\Stmt;
use PhpParser\ParserFactory;

class ClassBuilder
{
    const NOT_FOUND = -1;

    protected $namespace;

    protected $uses;

    protected $classname;

    protected $extend;

    protected $implements = [];

    protected $filename;

    protected $ast;

    /** @var Stmt\Class_ */
    protected $classStmt;

    public function __construct(string $namespace, array $uses, string $classname, string $filename, $extend = '', $implements = [])
    {
        $this->namespace = $namespace;
        $this->uses = $uses;
        $this->classname = $classname;
        $this->filename = $filename;
        $this->extend = $extend;
        $this->implements = $implements;
        $this->initStmt();
    }

    public function save()
    {
        $code = (new PrettyPrinter())->prettyPrintFile($this->ast);
        file_put_contents($this->filename, $code);
    }

    public function addGetter(string $propertyName)
    {
        $methodName = 'get' . Str::studly($propertyName);
        $propertyFetchExpr = new Node\Expr\PropertyFetch(new Node\Expr\Variable('this'), $propertyName);
        $stmts = [new Node\Stmt\Return_($propertyFetchExpr)];
        $this->addMethod($methodName, [], $stmts);
    }

    public function addSetter(string $propertyName)
    {
        $methodName = 'set' . Str::studly($propertyName);
        $stmts = [
            new Node\Stmt\Expression(new Node\Expr\Assign(
                new Node\Expr\PropertyFetch(new Node\Expr\Variable('this'), $propertyName),
                new Node\Expr\Variable($propertyName)
            ))
        ];
        $this->addMethod($methodName, [$propertyName], $stmts);
    }

    public function addMethod(string $name, array $params = [], array $expressions = [], int $modifier = Node\Stmt\Class_::MODIFIER_PUBLIC, $comments = [])
    {
        $fac = new BuilderFactory();
        $builder = $fac->method($name);
        $this->setBuilderModifier($builder, $modifier);
        if (count($comments) > 0) {
            $builder->setDocComment($this->buildDocCommentStr($comments));
        }
        foreach ($params as $param) {
            $builder->addParam(new Builder\Param($param));
        }
        $builder->addStmts($expressions);
        $index = $this->getClassMethodNodeIndex($name);
        $replace = true;
        if ($index == self::NOT_FOUND) {
            $replace = false;
            $index = 0;
            foreach ($this->classStmt->stmts as $k => $stmt) {
                if ($stmt instanceof Stmt\ClassMethod) {
                    $index = $k + 1;
                }
            }
            if ($index == 0 && count($this->classStmt->stmts) > 0) {
                $index = count($this->classStmt->stmts);
            }
        }
        array_splice($this->classStmt->stmts, $index, $replace ? 1 : 0, [$builder->getNode()]);
    }

    public function addConst(string $name, string $value, $comments = [])
    {
        $fac = new BuilderFactory();
        $builder = $fac->classConst($name, $value);
        if (count($comments) > 0) {
            $builder->setDocComment($this->buildDocCommentStr($comments));
        }
        $index = $this->getClassConstNodeIndex($name);
        $replace = true;
        if ($index == self::NOT_FOUND) {
            $replace = false;
            $index = 0;
            foreach ($this->classStmt->stmts as $k => $stmt) {
                if ($stmt instanceof Stmt\ClassConst) {
                    $index = $k + 1;
                }
            }
        }
        array_splice($this->classStmt->stmts, $index, $replace ? 1 : 0, [$builder->getNode()]);
    }

    /**
     * @param string $name
     * @param $defaultValue
     * @param int $modifier
     * @param string[] $comments
     * @return void
     */
    public function addProperty(string $name, $defaultValue = null, int $modifier = Stmt\Class_::MODIFIER_PRIVATE, array $comments = [])
    {
        $fac = new BuilderFactory();
        $builder = $fac->property($name)->setDefault($defaultValue);
        $this->setBuilderModifier($builder, $modifier);
        if (count($comments) > 0) {
            $builder->setDocComment($this->buildDocCommentStr($comments));
        }
        $index = $this->getPropertyNodeIndex($name);
        $replace = true;
        if ($index == self::NOT_FOUND) {
            $replace = false;
            $index = 0;
            foreach ($this->classStmt->stmts as $k => $stmt) {
                if ($stmt instanceof Stmt\Property) {
                    $index = $k + 1;
                }
            }
        }
        array_splice($this->classStmt->stmts, $index, $replace ? 1 : 0, [$builder->getNode()]);
    }

    /**
     * search for the class const node with given name in class node stmts
     */
    public function getClassConstNodeIndex(string $constName)
    {
        foreach ($this->classStmt->stmts as $k => $stmt) {
            if ($stmt instanceof Node\Stmt\ClassConst && $stmt->consts[0]->name->toString() == $constName) {
                return $k;
            }
        }
        return self::NOT_FOUND;
    }

    /**
     * search for the class property node with given name in class node stmts
     */
    public function getPropertyNodeIndex(string $propertyName)
    {
        foreach ($this->classStmt->stmts as $k => $stmt) {
            if ($stmt instanceof Node\Stmt\Property && $stmt->props[0]->name->toString() == $propertyName) {
                return $k;
            }
        }
        return self::NOT_FOUND;
    }

    /**
     * search for the class method node with given name in class node stmts
     */
    public function getClassMethodNodeIndex(string $methodName)
    {
        foreach ($this->classStmt->stmts as $k => $stmt) {
            if ($stmt instanceof Node\Stmt\ClassMethod && $stmt->name->toString() == $methodName) {
                return $k;
            }
        }
        return self::NOT_FOUND;
    }

    protected function buildDocCommentStr(array $comments): string
    {
        $firstLine = "/**\n";
        $lastLine = ' */';
        $body = '';
        foreach ($comments as $comment) {
            $body .= " * $comment\n";
        }
        return $firstLine . $body . $lastLine;
    }

    protected function initStmt()
    {
        $fac = new BuilderFactory();
        $class = $fac->class($this->classname);
        if (!empty($this->extend)) {
            $class->extend($this->extend);
        }
        if (!empty($this->implements)) {
            foreach ($this->implements as $implement) {
                if (!empty($implement)) {
                    $class->implement($implement);
                }
            }
        }

        if (file_exists($this->filename)) {
            $code = file_get_contents($this->filename);
            $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
            $this->ast = $parser->parse($code);
        } else {
            $this->ast = [];
        }

        //Find or create class stmt
        if (count($this->ast) > 0) {
            $hasNs = false;
            $hasTargetClass = false;
            foreach ($this->ast as $k => $item) {
                if ($item instanceof Stmt\Namespace_) {
                    $hasNs = true;
                    foreach ($item->stmts as $k2 => $stmt) {
                        if ($stmt instanceof Stmt\Class_) {
                            if ($stmt->name = $this->classname) {
                                $this->classStmt = &$this->ast[$k]->stmts[$k2];
                                $hasTargetClass = true;
                            }
                            break;
                        }
                    }
                    if (!$hasTargetClass) {
                        $i = count($this->ast[$k]->stmts);
                        $this->ast[$k]->stmts[] = $class->getNode();
                        $this->classStmt = &$this->ast[$k]->stmts[$i];
                    }
                    break;
                }
            }
            if (!$hasNs) {
                foreach ($this->ast as $k => $item) {
                    if ($item instanceof Stmt\Class_) {
                        $this->classStmt = &$this->ast[$k];
                        $hasTargetClass = true;
                        break;
                    }
                }
                if (!$hasTargetClass) {
                    $this->ast[] = $class->getNode();
                    $this->classStmt = &$this->ast[count($this->ast) - 1];
                }
            }
        } else {
            $ns = $fac->namespace($this->namespace);
            foreach ($this->uses as $use) {
                $ns->addStmt($fac->use($use));
            }
            $this->ast = [$ns->getNode()];
            $i = count($this->ast[0]->stmts);
            $this->ast[0]->stmts[] = $class->getNode();
            $this->classStmt = &$this->ast[0]->stmts[$i];
        }
    }

    protected function setBuilderModifier(Builder $builder, int $modifier)
    {
        if (!method_exists($builder, 'makePublic')) {
            return;
        }
        if ($builder instanceof Method || $builder instanceof Property) {
            switch ($modifier) {
                case Stmt\Class_::MODIFIER_PRIVATE:
                    $builder->makePrivate();
                    break;
                case Stmt\Class_::MODIFIER_PUBLIC:
                    $builder->makePublic();
                    break;
                default:
                    $builder->makeProtected();
            }
        }
    }
}
