<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class DetailsTest extends DocumentationTestCase
{
    #[Test]
    public function it_documents_the_details_of_each_resource_in_its_own_component(): void
    {
        $document = $this->document();
        $responses = array_map(fn (string $path): array => $document['paths'][$path]['get']['responses'][200], ['/notes', '/shows', '/stages']);

        $this->assertSame(['#/components/schemas/NoteDetails', '#/components/schemas/ShowDetails', '#/components/schemas/StageDetails'], array_map(fn (array $response): string => $response['content']['application/json']['schema']['$ref'], $responses));
        $this->assertSame(['data'], $document['components']['schemas']['ShowDetails']['required']);
    }

    /** @param list<string> $names */
    #[Test]
    #[DataProvider('names')]
    public function it_lists_the_names_of_the_declarations_of_a_resource(string $list, string $key, array $names): void
    {
        $data = $this->document()['components']['schemas']['ShowDetails']['properties']['data']['properties'];
        $items = $data[$list]['items'];

        $this->assertSame($names, $key === '' ? $items['enum'] : $items['properties'][$key]['enum']);
    }

    /** @return array<string, array{string, string, list<string>}> */
    public static function names(): array
    {
        return [
            'fields' => ['fields', 'name', ['id', 'title', 'summary', 'price', 'seats', 'featured', 'starts_on', 'starts_at', 'status', 'level', 'settings', 'secret', 'headline']],
            'relations' => ['relations', 'relation', ['stage', 'artists', 'comments', 'labels']],
            'scopes' => ['scopes', 'name', ['startingFrom']],
            'instructions' => ['instructions', 'uriKey', ['only-featured']],
            'actions' => ['actions', 'uriKey', ['reschedule-shows', 'cancel-shows', 'import-shows', 'archive-shows']],
            'metrics' => ['metrics', 'uriKey', ['show-count', 'shows-per-month', 'shows-by-status', 'top-stages', 'seats-goal', 'occupancy']],
            'gates' => ['gates', '', ['view', 'create', 'update', 'delete', 'restore', 'forceDelete', 'feature']],
        ];
    }
}
