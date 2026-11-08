<?php

namespace Lomkit\Rest\Scramble\Search;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Text\TextCapabilities;

final readonly class EngineTextType
{
    public function __construct(private TextCapabilities $capabilities) {}

    public function text(): ObjectType
    {
        return Schemas::closed([
            'value' => TextSearchType::value($this->capabilities),
            'mode' => (new StringType)->enum($this->capabilities->modeNames()),
        ], [], 'Words to search in the search index of the resource; the index decides which attributes they match');
    }

    public function trashed(): StringType
    {
        $type = (new StringType)->enum(array_column(Trashed::cases(), 'value'));

        if (! $this->capabilities->trashed) {
            $type->setDescription('Cannot be combined with a text search: the search index of the resource does not keep the trashed models');
        }

        return $type;
    }
}
