<?php

namespace Lomkit\Rest\Tests\Feature\Fields;

use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Feature\Fields\Stubs\GatesFieldResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ReservedFieldNameTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Publishing];
    }

    #[Test]
    #[DataProvider('gatesKeys')]
    public function it_refuses_a_field_named_like_the_gates_key(string $key): void
    {
        config(['rest.gates.key' => $key]);

        $this->expectExceptionObject(InvalidResourceException::gatesKeyField(GatesFieldResource::class, $key));

        $this->fields();
    }

    /** @return array<string, array{string}> */
    public static function gatesKeys(): array
    {
        return [
            'the default key' => ['gates'],
            'a configured key' => ['abilities'],
        ];
    }

    #[Test]
    public function it_accepts_a_field_named_like_a_key_the_config_does_not_use(): void
    {
        config(['rest.gates.key' => 'permissions']);

        $this->assertSame(['id', 'gates', 'abilities'], $this->fields()->names());
    }

    private function fields(): FieldSet
    {
        $context = new RestContext(RestRequest::create('/api/posts/search', 'POST'), $this->app);

        return $context->resource(GatesFieldResource::class)->getFields();
    }
}
