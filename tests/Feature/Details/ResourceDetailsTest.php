<?php

namespace Lomkit\Rest\Tests\Feature\Details;

use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\CategoryResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\AuthorResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ResourceDetailsTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing, new Catalog];
    }

    #[Test]
    public function it_describes_the_scopes_actions_and_instructions_as_typed_inputs(): void
    {
        $response = $this->getJson('/api/posts');

        $response->assertOk()->assertExactJson(['data' => [
            'actions' => [
                [
                    'name' => 'Publish Posts',
                    'uriKey' => 'publish-posts',
                    'fields' => [
                        ['name' => 'at', 'type' => 'datetime', 'nullable' => true, 'default' => null, 'rules' => ['nullable', 'date'], 'meta' => []],
                        ['name' => 'notify', 'type' => 'boolean', 'nullable' => false, 'default' => true, 'rules' => ['boolean'], 'meta' => []],
                    ],
                    'meta' => ['color' => '#FFFFFF'],
                    'standalone' => false,
                    'targeted' => false,
                ],
                [
                    'name' => 'Queued Publish Posts',
                    'uriKey' => 'queued-publish-posts',
                    'fields' => [
                        ['name' => 'at', 'type' => 'datetime', 'nullable' => true, 'default' => null, 'rules' => ['nullable', 'date'], 'meta' => []],
                        ['name' => 'notify', 'type' => 'boolean', 'nullable' => false, 'default' => true, 'rules' => ['boolean'], 'meta' => []],
                    ],
                    'meta' => [],
                    'standalone' => false,
                    'targeted' => false,
                ],
                [
                    'name' => 'Batched Publish Posts',
                    'uriKey' => 'batched-publish-posts',
                    'fields' => [
                        ['name' => 'at', 'type' => 'datetime', 'nullable' => true, 'default' => null, 'rules' => ['nullable', 'date'], 'meta' => []],
                        ['name' => 'notify', 'type' => 'boolean', 'nullable' => false, 'default' => true, 'rules' => ['boolean'], 'meta' => []],
                    ],
                    'meta' => [],
                    'standalone' => false,
                    'targeted' => false,
                ],
                [
                    'name' => 'Change Status',
                    'uriKey' => 'change-status',
                    'fields' => [
                        ['name' => 'status', 'type' => 'enum', 'nullable' => false, 'default' => null, 'rules' => ['enum', 'required'], 'meta' => [], 'values' => ['draft', 'review', 'published']],
                        ['name' => 'at', 'type' => 'datetime', 'nullable' => false, 'default' => null, 'rules' => ['date', 'required_if:status,published'], 'meta' => []],
                    ],
                    'meta' => [],
                    'standalone' => false,
                    'targeted' => false,
                ],
            ],
            'instructions' => [
                [
                    'name' => 'Highlight Posts',
                    'uriKey' => 'highlight-posts',
                    'fields' => [
                        ['name' => 'featured', 'type' => 'boolean', 'nullable' => false, 'default' => true, 'rules' => ['boolean'], 'meta' => []],
                        ['name' => 'min_views', 'type' => 'integer', 'nullable' => true, 'default' => null, 'rules' => ['nullable', 'integer'], 'meta' => []],
                    ],
                    'meta' => ['group' => 'ranking'],
                ],
                [
                    'name' => 'In Status',
                    'uriKey' => 'in-status',
                    'fields' => [
                        ['name' => 'status', 'type' => 'enum', 'nullable' => false, 'default' => null, 'rules' => ['enum', 'required'], 'meta' => [], 'values' => ['draft', 'review', 'published']],
                        ['name' => 'since', 'type' => 'date', 'nullable' => false, 'default' => null, 'rules' => ['date', 'required_if:status,published'], 'meta' => []],
                    ],
                    'meta' => [],
                ],
            ],
            'scout_instructions' => [],
            'fields' => ['id', 'title', 'summary', 'status', 'featured', 'views', 'rating', 'published_on', 'published_at', 'metadata'],
            'scout_fields' => [],
            'limits' => [10, 25, 50],
            'scopes' => [
                [
                    'name' => 'publishedSince',
                    'fields' => [
                        ['name' => 'since', 'type' => 'date', 'nullable' => false, 'default' => null, 'rules' => ['date'], 'meta' => []],
                    ],
                    'meta' => [],
                ],
                [
                    'name' => 'popular',
                    'fields' => [
                        ['name' => 'min_views', 'type' => 'integer', 'nullable' => false, 'default' => 100, 'rules' => ['integer'], 'meta' => []],
                    ],
                    'meta' => [],
                ],
                [
                    'name' => 'viewedBetween',
                    'fields' => [
                        ['name' => 'min', 'type' => 'integer', 'nullable' => false, 'default' => null, 'rules' => ['integer', 'min:1'], 'meta' => []],
                        ['name' => 'max', 'type' => 'integer', 'nullable' => true, 'default' => null, 'rules' => ['nullable', 'integer'], 'meta' => []],
                    ],
                    'meta' => [],
                ],
                [
                    'name' => 'featured',
                    'fields' => [
                        ['name' => 'featured', 'type' => 'boolean', 'nullable' => true, 'default' => true, 'rules' => ['nullable', 'boolean'], 'meta' => []],
                    ],
                    'meta' => [],
                ],
                [
                    'name' => 'reaching',
                    'fields' => [
                        ['name' => 'reach', 'type' => 'enum', 'nullable' => false, 'default' => null, 'rules' => ['enum'], 'meta' => [], 'values' => [1, 2, 3]],
                    ],
                    'meta' => [],
                ],
            ],
            'relations' => [
                [
                    'resource' => AuthorResource::class,
                    'relation' => 'author',
                    'constraints' => [
                        'required_on_creation' => false,
                        'prohibited_on_creation' => false,
                        'required_on_update' => false,
                        'prohibited_on_update' => false,
                    ],
                    'name' => 'BelongsTo',
                ],
            ],
            'rules' => ['all' => [], 'create' => [], 'update' => []],
        ]]);
    }

    #[Test]
    public function it_describes_the_relations_and_the_scout_instructions(): void
    {
        $pricedAt = [
            'name' => 'Priced At',
            'uriKey' => 'priced-at',
            'fields' => [
                ['name' => 'price', 'type' => 'integer', 'nullable' => false, 'default' => null, 'rules' => ['integer', 'required'], 'meta' => []],
            ],
            'meta' => [],
        ];

        $response = $this->getJson('/api/products');

        $response->assertOk()->assertExactJson(['data' => [
            'actions' => [],
            'instructions' => [$pricedAt],
            'scout_instructions' => [
                $pricedAt,
                [
                    'name' => 'Priced Between',
                    'uriKey' => 'priced-between',
                    'fields' => [
                        ['name' => 'min', 'type' => 'integer', 'nullable' => false, 'default' => 0, 'rules' => ['integer'], 'meta' => []],
                        ['name' => 'max', 'type' => 'integer', 'nullable' => false, 'default' => null, 'rules' => ['integer', 'required'], 'meta' => []],
                    ],
                    'meta' => [],
                ],
            ],
            'fields' => ['id', 'category_id', 'name', 'price'],
            'scout_fields' => ['category_id', 'price'],
            'limits' => [10, 25, 50],
            'scopes' => [],
            'relations' => [
                [
                    'resource' => CategoryResource::class,
                    'relation' => 'category',
                    'constraints' => [
                        'required_on_creation' => false,
                        'prohibited_on_creation' => false,
                        'required_on_update' => false,
                        'prohibited_on_update' => false,
                    ],
                    'name' => 'BelongsTo',
                ],
            ],
            'rules' => ['all' => [], 'create' => [], 'update' => []],
        ]]);
    }
}
