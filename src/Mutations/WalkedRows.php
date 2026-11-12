<?php

namespace Lomkit\Rest\Mutations;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Fields\MutationOperation;
use Lomkit\Rest\Http\Resource;

final class WalkedRows
{
    /** @var array<int, Model> */
    private array $drafts = [];

    public function __construct(private readonly RowSet $rows) {}

    public function row(Resource $resource, mixed $key): ?Model
    {
        return is_int($key) || is_string($key) ? $this->rows->peek($resource, $key) : null;
    }

    public function draft(Resource $resource, MutationNode $node): Model
    {
        return $this->drafts[spl_object_id($node)] ??= $resource::newModel();
    }

    /** @return list<Model> */
    public function of(Resource $resource, MutationNode $node): array
    {
        if ($node->operation === MutationOperation::Create) {
            $draft = $this->draft($resource, $node);
            unset($this->drafts[spl_object_id($node)]);

            return [$draft];
        }

        $rows = [];

        foreach (is_array($node->key) ? $node->key : [$node->key] as $key) {
            $row = $this->row($resource, $key);

            if ($row !== null) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
