<?php

namespace Lomkit\Rest\Scramble\Search;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\RelevanceClause;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Scramble\Schemas;
use Lomkit\Rest\Text\ResolvedText;
use Lomkit\Rest\Text\TextBackend;
use Lomkit\Rest\Text\TextCapabilities;

final readonly class TextSearchType
{
    public function __construct(
        private Resource $resource,
        private ResolvedText $text,
    ) {}

    public static function of(Resource $resource): self
    {
        return new self($resource, $resource->context()->text($resource));
    }

    public static function value(TextCapabilities $capabilities): StringType
    {
        $value = new StringType;
        $value->setMax($capabilities->maxLength);
        $value->nullable(true);

        return $value;
    }

    /**
     * @param  list<string>  $sortable
     * @return list<string>
     */
    public function sortNames(array $sortable): array
    {
        return $this->text->capabilities?->relevance === true ? [...$sortable, RelevanceClause::FIELD] : $sortable;
    }

    public function text(): ?ObjectType
    {
        $capabilities = $this->text->capabilities;

        if ($this->text->backend === TextBackend::None || $capabilities === null) {
            return null;
        }

        if ($this->text->backend === TextBackend::Engine) {
            return (new EngineTextType($capabilities))->text();
        }

        return Schemas::closed([
            'value' => self::value($capabilities),
            'mode' => (new StringType)->enum($capabilities->modeNames()),
        ], [], 'Text to search in the searchable fields');
    }

    public function trashed(): ?StringType
    {
        if (! Trashed::supportedBy($this->resource::modelClass())) {
            return null;
        }

        $capabilities = $this->text->capabilities;

        if ($this->text->backend === TextBackend::Engine && $capabilities !== null) {
            return (new EngineTextType($capabilities))->trashed();
        }

        return (new StringType)->enum(array_column(Trashed::cases(), 'value'));
    }
}
