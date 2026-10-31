<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Console\Generation\Schema\Table;
use Lomkit\Rest\Console\Generation\Schema\TableReader;
use Lomkit\Rest\Exceptions\InvalidModelTableException;
use ReflectionClass;

final readonly class ModelTable
{
    private function __construct(public string $class, public Model $model) {}

    public static function find(?ClassReference $reference): ?self
    {
        $class = $reference?->fqcn();

        if ($class === null || ! is_subclass_of($class, Model::class) || ! (new ReflectionClass($class))->isInstantiable()) {
            return null;
        }

        return new self($class, new $class);
    }

    public static function of(?ClassReference $reference): self
    {
        return self::find($reference) ?? throw match (true) {
            $reference === null => InvalidModelTableException::noModel(),
            ! $reference->exists() => InvalidModelTableException::missingModel($reference->fqcn()),
            is_subclass_of($reference->fqcn(), Model::class) => InvalidModelTableException::notInstantiable($reference->fqcn()),
            default => InvalidModelTableException::notAModel($reference->fqcn()),
        };
    }

    public function name(): string
    {
        return $this->model->getTable();
    }

    public function exists(): bool
    {
        return $this->reader()->exists($this->name());
    }

    public function read(): Table
    {
        if (! $this->exists()) {
            throw InvalidModelTableException::missingTable($this->name(), $this->class);
        }

        return $this->reader()->read($this->name());
    }

    private function reader(): TableReader
    {
        return new TableReader($this->model->getConnection()->getSchemaBuilder());
    }
}
