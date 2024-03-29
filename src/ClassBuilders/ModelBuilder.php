<?php


namespace ModelCreator\ClassBuilders;

use ModelCreator\Field\ModelField;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use PhpParser\BuilderFactory;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Return_;

class ModelBuilder implements ClassBuilderInterface
{
    /** @var ClassBuilder */
    protected $builder;

    /** @var BuilderFactory */
    protected $fac;

    const OPTION_NAME_USE_CONST = 'const';

    const FIELD_CONST_PREFIX = 'FIELD_';

    protected $command;

    protected $useConst;

    protected $className;

    protected $nameSpace = 'App\\Models';

    protected $basePath;
    /**
     * @var ModelField[]
     */
    protected $fields;

    public function __construct(Command $command)
    {
        $this->command = $command;
        $options = $command->options();
        $this->useConst = $options[self::OPTION_NAME_USE_CONST] ?? false;
        $this->basePath = base_path('app/Models');
        $this->fac = new BuilderFactory();
    }

    public function setTableName(string $name): void
    {
        $this->className = Str::studly($name);
        $filename = $this->getFilename();
        if (file_exists($filename)) {
            $this->command->info(sprintf('Class file %s already exists.', $filename));
        }
    }

    public function existsField(ModelField $field): bool
    {
        //TODO: check if the field is already exists.
        return false;
    }

    public function addField(ModelField $field): void
    {
        $this->fields[] = $field;
    }

    /**
     * generate getter method of the field
     */
    public function addFieldGetter(ModelField $field)
    {
        $fieldName = $field->getName();
        $methodName = 'get' . Str::studly($fieldName);
        $getAttribute = new MethodCall(new Variable('this'), 'getAttribute', $this->fac->args([$fieldName]));
        $this->builder->addMethod($methodName, [], [new Return_($getAttribute)]);
    }

    /**
     * generate setter method of the field
     */
    public function addFieldSetter(ModelField $field)
    {
        $fieldName = $field->getName();
        $methodName = 'set' . Str::studly($fieldName);
        $expressions = [new MethodCall(new Variable('this'), 'setAttribute', $this->fac->args([$fieldName, $this->fac->var($fieldName)]))];
        $this->builder->addMethod($methodName, [$fieldName], $expressions);
    }

    /**
     * generate getter method of the field
     */
    public function addFieldGetterWithConst(ModelField $field)
    {
        $fieldName = $field->getName();
        $methodName = 'get' . Str::studly($fieldName);
        $constName = $this->makeFieldConstName($fieldName);
        $constFetch = new ConstFetch(new Name(sprintf('self::%s', $constName)));
        $getAttribute = new MethodCall(new Variable('this'), 'getAttribute', [new Arg($constFetch)]);
        $this->builder->addMethod($methodName, [], [new Return_($getAttribute)]);
    }

    /**
     * generate setter method of the field
     */
    public function addFieldSetterWithConst(ModelField $field)
    {
        $fieldName = $field->getName();
        $methodName = 'set' . Str::studly($fieldName);
        $constName = $this->makeFieldConstName($fieldName);
        $constFetch = new ConstFetch(new Name(sprintf('self::%s', $constName)));
        $args = [new Arg($constFetch), new Arg(new Variable($fieldName))];
        $expressions = [
            new MethodCall(new Variable('this'), 'setAttribute', $args),
        ];
        $this->builder->addMethod($methodName, [$fieldName], $expressions);
    }

    public function addFieldConst(ModelField $field)
    {
        $fieldName = $field->getName();
        $constName = $this->makeFieldConstName($fieldName);
        $this->builder->addConst($constName, $fieldName, $field->makeFieldComment($field));
    }

    private function getFilename(): string
    {
        return sprintf('%s/%s.php', $this->basePath, $this->className);
    }

    protected function makeFieldConstName($fieldName): string
    {
        return self::FIELD_CONST_PREFIX . strtoupper($fieldName);
    }

    public function __destruct()
    {
        $this->builder = new ClassBuilder($this->nameSpace, [Model::class], $this->className, $this->getFilename(), "Model", []);
        foreach ($this->fields as $field) {
            if ($this->useConst) {
                $this->addFieldConst($field);
                $this->addFieldGetterWithConst($field);
                $this->addFieldSetterWithConst($field);
            } else {
                $this->addFieldGetter($field);
                $this->addFieldSetter($field);
            }
        }
        $this->builder->save();
        $this->command->info(sprintf("Model file %s created successfully.", $this->getFilename()));
    }
}
