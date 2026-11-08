<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Validation\Stubs\RuledMutateRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\Category;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class AddedMutateRulesTest extends PackageTestCase
{
    use AssertsExactErrors;

    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        RuledMutateRequest::$before = [];
        RuledMutateRequest::$added = [];
        $this->app->bind(MutateRequest::class, RuledMutateRequest::class);
    }

    #[Test]
    public function it_adds_an_application_rule_to_the_package_rules_of_an_entry_key(): void
    {
        RuledMutateRequest::$added = ['mutate.0.attributes.name' => ['max:5']];

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Lighting']],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.attributes.name' => ['The mutate.0.attributes.name field must not be greater than 5 characters.']]);
    }

    #[Test]
    public function it_expands_an_application_wildcard_rule_over_the_entries(): void
    {
        RuledMutateRequest::$added = ['mutate.*.attributes.name' => ['max:5']];

        $response = $this->postJson('/api/categories/mutate', ['mutate' => self::lightingAndDesks()]);

        $this->assertExactErrors($response, ['mutate.0.attributes.name' => ['The mutate.0.attributes.name field must not be greater than 5 characters.']]);
        $this->assertSame(0, Category::query()->count());
    }

    #[Test]
    public function it_expands_an_application_wildcard_rule_written_before_the_parent_rules(): void
    {
        RuledMutateRequest::$before = ['mutate.*.attributes.name' => ['max:5']];

        $response = $this->postJson('/api/categories/mutate', ['mutate' => self::lightingAndDesks()]);

        $this->assertExactErrors($response, ['mutate.0.attributes.name' => ['The mutate.0.attributes.name field must not be greater than 5 characters.']]);
        $this->assertSame(0, Category::query()->count());
    }

    #[Test]
    public function it_runs_no_application_wildcard_rule_under_a_refused_list(): void
    {
        config(['rest.limits.sizes.mutate' => 1]);
        RuledMutateRequest::$added = ['mutate.*.attributes.name' => ['max:5']];

        $response = $this->postJson('/api/categories/mutate', ['mutate' => self::lightingAndDesks()]);

        $this->assertExactErrors($response, ['mutate' => ['The mutate field must not have more than 1 items.']]);
    }

    #[Test]
    public function it_applies_an_application_rule_to_an_entry_key_that_the_package_does_not_read(): void
    {
        RuledMutateRequest::$added = ['mutate.*.flag' => ['boolean']];

        $refused = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Desks'], 'flag' => 'maybe'],
        ]]);
        $accepted = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Desks'], 'flag' => true],
        ]]);

        $this->assertExactErrors($refused, ['mutate.0.flag' => ['The mutate.0.flag field must be true or false.']]);
        $accepted->assertOk()->assertExactJson(['operations' => [['operation' => 'create', 'key' => 1]]]);
        $this->assertSame(['Desks'], Category::query()->pluck('name')->all());
    }

    #[Test]
    public function it_applies_an_application_rule_to_relations_that_are_absent(): void
    {
        RuledMutateRequest::$added = ['mutate.*.relations' => ['required']];

        $response = $this->postJson('/api/categories/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Seating']],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.relations' => ['The mutate.0.relations field is required.']]);
        $this->assertSame(0, Category::query()->count());
    }

    /** @return list<array<string, mixed>> */
    private static function lightingAndDesks(): array
    {
        return [
            ['operation' => 'create', 'attributes' => ['name' => 'Lighting']],
            ['operation' => 'create', 'attributes' => ['name' => 'Desks']],
        ];
    }
}
