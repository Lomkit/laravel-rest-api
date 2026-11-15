<?php

namespace Lomkit\Rest\Extensions\Contexts;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Scramble\Components\MetaSchemaRecord;

final readonly class MetaDocumentationContext
{
    /**
     * @internal
     *
     * @param  class-string<Resource>  $resource
     */
    public function __construct(
        public string $resource,
        private MetaSchemaRecord $record,
    ) {}

    public function searchMeta(ObjectType $schema): void
    {
        $this->record->search = $schema;
    }

    public function detailsMeta(ObjectType $schema): void
    {
        $this->record->details = $schema;
    }
}
