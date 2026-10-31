<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Query\Clauses\RelevanceClause;
use Lomkit\Rest\Query\Trashed;
use Lomkit\Rest\Text\ResolvedText;
use Lomkit\Rest\Text\TextBackend;

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

    /**
     * @param  list<string>  $sortable
     * @return list<string>
     */
    public function sortNames(array $sortable): array
    {
        return $this->text->capabilities?->relevance === true ? [...$sortable, RelevanceClause::FIELD] : $sortable;
    }

    public function addTo(ObjectType $search): void
    {
        $engine = $this->text->backend === TextBackend::Engine && $this->text->capabilities !== null
            ? new EngineTextType($this->text->capabilities)
            : null;

        if ($this->text->backend !== TextBackend::None) {
            $search->addProperty('text', $engine?->text() ?? $this->textType());
        }

        if (Trashed::supportedBy($this->resource::modelClass())) {
            $search->addProperty('trashed', $engine?->trashed() ?? (new StringType)->enum(array_column(Trashed::cases(), 'value')));
        }
    }

    private function textType(): ObjectType
    {
        $type = new ObjectType;
        $type->addProperty('value', new StringType);

        if ($this->text->capabilities !== null) {
            $type->addProperty('mode', (new StringType)->enum($this->text->capabilities->modeNames()));
        }

        $type->setDescription('Text to search in the searchable fields');

        return $type;
    }
}
