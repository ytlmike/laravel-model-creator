<?php

namespace ModelCreator\ClassBuilders;

use ModelCreator\Field\ModelField;
use ModelCreator\FileSystem\Dir;
use PhpParser\BuilderFactory;
use ModelCreator\NodeBuilders\Closure;
use ModelCreator\Printers\PrettyPrinter;
use Illuminate\Console\Command;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use PhpParser\Node;
use PhpParser\Builder;

class MigrationBuilder implements ClassBuilderInterface
{
    const ACTION_CREATE = 'create';
    const ACTION_MODIFY = 'modify';

    protected $command;

    protected $tableName;

    protected $prefix;

    protected $basePath;

    protected $action = self::ACTION_CREATE;

    /**
     * @var ModelField[]
     */
    protected $fields;

    public function __construct(Command $command)
    {
        $this->command = $command;
        $this->prefix = date('Y_m_d_His');
        $this->basePath = base_path('database/migrations');
    }

    public function setTableName(string $name): void
    {
        $this->tableName = $name;

        //check if exists create table migrate
        Dir::walk($this->basePath, false, function ($filename) {
            if (Str::endsWith($filename, $this->getFilenameSuffix())) {
                $this->action = self::ACTION_MODIFY;
            }
        });
    }

    public function existsField(ModelField $field): bool
    {
        //TODO: check if the field is already exists.
        return false;
    }

    /**
     * @param ModelField $field
     */
    public function addField(ModelField $field): void
    {
        $this->fields[] = $field;
    }

    public function __destruct()
    {
        $this->writeCode();
        $this->command->info(sprintf("Migrate file %s created successfully.", $this->getFilename()));
    }

    private function getFilename(): string
    {
        return sprintf('%s/%s_%s.php', $this->basePath, $this->prefix, $this->getFilenameSuffix());
    }

    private function getFilenameSuffix(): string
    {
        return sprintf('%s_%s_table', $this->action, $this->tableName);
    }

    private function getClassName(): string
    {
        return sprintf('%s%sTable', Str::studly($this->action), Str::studly($this->tableName));
    }

    private function writeCode()
    {
        $fac = new BuilderFactory();
        $node = $fac->namespace('Database\Migrations')
            ->addStmt($fac->use(Migration::class))
            ->addStmt($fac->use(Blueprint::class))
            ->addStmt($fac->use(Schema::class))
            ->addStmt($fac->class($this->getClassName())
                ->extend('Migration')
                ->addStmt($this->buildUpper())
                ->addStmt($this->buildDowner())
            )->getNode();
        $code = (new PrettyPrinter())->prettyPrintFile([$node]);
        file_put_contents($this->getFilename(), $code);
    }

    private function buildUpper(): Builder\Method
    {
        $fac = new BuilderFactory();
        $method = $this->action == self::ACTION_CREATE ? 'create' : 'table';
        $stmts = [];
        if (!empty($this->fields)) {
            foreach ($this->fields as $field) {
                $stmts[] = $this->buildCreateFieldExpr($field);
            }
        }
        return $fac->method('up')->addStmt($fac->staticCall('Schema', $method, $fac->args([$this->tableName, $this->buildClosure($stmts)])));
    }

    private function buildDowner(): Builder\Method
    {
        $fac = new BuilderFactory();
        if ($this->action = self::ACTION_CREATE) {
            return $fac->method('down')->addStmt($fac->staticCall('Schema', 'dropIfExists', $fac->args([$this->tableName])));
        } else {
            $fieldNames = [];
            if (!empty($this->fields)) {
                foreach ($this->fields as $field) {
                    $fieldNames[] = $field->getName();
                }
            }
            $stmts = [$fac->methodCall($fac->var('table'), 'dropColumn', $fac->args([$fieldNames]))];
            return $fac->method('down')->addStmt($fac->staticCall('Schema', 'table', $fac->args([$this->tableName, $this->buildClosure($stmts)])));
        }
    }

    private function buildClosure($stmts): Node
    {
        $closureBuilder = new Closure();
        $closureBuilder->addParam('table', 'Blueprint');
        foreach ($stmts as $stmt) {
            $closureBuilder->addStmt($stmt);
        }
        return $closureBuilder->getNode();
    }

    private function buildCreateFieldExpr(ModelField $field): Node\Expr\MethodCall
    {
        $fac = new BuilderFactory();
        if ($field->isPrimaryKey()) {
            return $fac->methodCall($fac->var('table'), 'id');
        }
        $createField = $fac->methodCall($fac->var('table'), $field->getFieldType()->getMigrateMethod(), $fac->args([$field->getName(), $field->getLength()]));
        if (!empty($comment)) {
            $createField = $fac->methodCall($createField, 'comment', $fac->args([$comment]));
        }
        if ($field->getDefaultValue() !== null) {
            $createField = $fac->methodCall($createField, 'default', $fac->args([$field->getDefaultValue()]));
        }
        if (!$field->getNullable()) {
            $createField = $fac->methodCall($createField, 'nullable', $fac->args([$field->getNullable()]));
        }
        $method = '';
        if ($field->getIndex() == ModelField::INDEX_NORMAL) {
            $method = 'index';
        }
        if ($field->getIndex() == ModelField::INDEX_UNIQUE) {
            $method = 'unique';
        }
        if (!empty($method)) {
            $createField = $fac->methodCall($createField, $method);
        }
        return $createField;
    }

}
