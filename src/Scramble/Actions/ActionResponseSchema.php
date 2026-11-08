<?php

namespace Lomkit\Rest\Scramble\Actions;

use Dedoc\Scramble\Support\Generator\Reference;
use Dedoc\Scramble\Support\Generator\Types\BooleanType;
use Dedoc\Scramble\Support\Generator\Types\IntegerType;
use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Dedoc\Scramble\Support\Generator\Types\Type;
use Lomkit\Rest\Scramble\ComponentRegistry;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Scramble\Types\OneOf;

final readonly class ActionResponseSchema
{
    private const array RESULTS = [
        'message' => 'A message for the user',
        'danger' => 'A warning for the user',
        'download' => 'A file for the client to download',
        'redirect' => 'A URL for the client to open',
        'created' => 'The keys of the rows that the action created',
        'data' => 'Data for the client',
    ];

    public function __construct(
        private ComponentRegistry $components,
    ) {}

    public function operate(bool $batchable): Reference
    {
        $name = $batchable ? 'RestBatchableActionResponse' : 'RestActionResponse';

        return $this->components->schema($name, self::class, function () use ($batchable): Type {
            $impacted = new IntegerType;
            $impacted->setMin(0);
            $impacted->setDescription('The number of rows that the action ran on');
            $batch = (new StringType)->setDescription('The id of the batch of the queued jobs of the action');
            $result = $this->result()->nullable(true);

            return Schemas::required(['data' => Schemas::required($batchable ? ['impacted' => $impacted, 'batch' => $batch, 'result' => $result] : ['impacted' => $impacted, 'result' => $result])]);
        });
    }

    public function progress(): Reference
    {
        return $this->components->schema('RestBatchProgress', self::class, function (): Type {
            $progress = new IntegerType;
            $progress->setMin(0);
            $progress->setMax(100);
            $progress->setDescription('The share of the jobs that ran, in percent');

            return Schemas::required(['data' => Schemas::required([
                'id' => new StringType,
                'total_jobs' => new IntegerType,
                'pending_jobs' => new IntegerType,
                'failed_jobs' => new IntegerType,
                'progress' => $progress,
                'finished' => new BooleanType,
                'cancelled' => new BooleanType,
            ])]);
        });
    }

    private function result(): Reference
    {
        return $this->components->schema('RestActionResult', self::class, function (): Type {
            $branches = [];

            foreach (self::RESULTS as $type => $description) {
                $branches[] = [$type, 'RestActionResult'.ucfirst($type), fn (): Type => self::branch($type, $description)];
            }

            return $this->components->discriminated('type', self::class, $branches);
        });
    }

    private static function branch(string $type, string $description): ObjectType
    {
        $data = (new ObjectType)->setDescription($type === 'data' ? 'The data of the action' : 'Present when the action adds data to its result with `withData()`');
        $values = match ($type) {
            'message', 'danger' => ['message' => new StringType],
            'download' => ['url' => new StringType, 'name' => (new StringType)->setDescription('The name of the file')],
            'redirect' => ['url' => new StringType],
            'created' => ['keys' => Schemas::list(new OneOf([new IntegerType, new StringType]), 'In the order that the action gives the rows')],
            default => [],
        };

        return Schemas::required(['type' => (new StringType)->const($type), ...$values, 'data' => $data], $type === 'data' ? [] : ['data'], $description);
    }
}
