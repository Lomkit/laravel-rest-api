<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Http\Requests\OperateRequest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Rules\OneOf;
use Lomkit\Rest\Rules\Search\SearchAggregate;
use Lomkit\Rest\Rules\Search\SearchFilter;
use Lomkit\Rest\Rules\Search\SearchInstruction;
use Lomkit\Rest\Rules\Search\SearchScope;
use Lomkit\Rest\Rules\Search\SearchSelect;
use Lomkit\Rest\Rules\Search\SearchSort;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostResource;
use Lomkit\Rest\Tests\Fixtures\Publishing\Publishing;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class NameListRulesTest extends PackageTestCase
{
    private const array PRODUCT_FIELDS = ['id', 'category_id', 'name', 'price'];

    private const array SCOUT_FIELDS = ['category_id', 'price'];

    protected function fixtures(): array
    {
        return [new Catalog, new Publishing];
    }

    #[Test]
    public function it_checks_selected_fields_against_a_one_of_rule(): void
    {
        $rules = (new SearchSelect)->setResource($this->products())->buildValidationRules('select', ['field' => 'name']);

        $this->assertSame(self::PRODUCT_FIELDS, $this->oneOf($rules['select.field']));
    }

    #[Test]
    public function it_checks_sorted_fields_against_a_one_of_rule(): void
    {
        $sql = (new SearchSort)->setResource($this->products())->buildValidationRules('sort', ['field' => 'name']);
        $text = (new SearchSort)->setResource($this->products())->forTextSearch()->buildValidationRules('sort', ['field' => 'price']);

        $this->assertSame(self::PRODUCT_FIELDS, $this->oneOf($sql['sort.field']));
        $this->assertSame(self::SCOUT_FIELDS, $this->oneOf($text['sort.field']));
    }

    #[Test]
    public function it_checks_the_filtered_fields_of_a_text_search_against_a_one_of_rule(): void
    {
        $rules = (new SearchFilter)->setResource($this->products())->forTextSearch()->buildValidationRules('filter', ['field' => 'price']);

        $this->assertSame(self::SCOUT_FIELDS, $this->oneOf($rules['filter.field']));
    }

    #[Test]
    public function it_checks_aggregated_fields_against_a_one_of_rule(): void
    {
        $rules = (new SearchAggregate)->setResource($this->products())->buildValidationRules('aggregate', ['relation' => 'category']);

        $this->assertSame(['id', 'name'], $this->oneOf($rules['aggregate.field']));
    }

    #[Test]
    public function it_checks_instruction_names_and_their_field_names_against_a_one_of_rule(): void
    {
        $instruction = ['name' => 'priced-at', 'fields' => [['name' => 'price', 'value' => 39]]];
        $sql = (new SearchInstruction)->setResource($this->products())->buildValidationRules('instruction', $instruction);
        $text = (new SearchInstruction)->setResource($this->products())->forTextSearch()->buildValidationRules('instruction', $instruction);

        $this->assertSame(['priced-at'], $this->oneOf($sql['instruction.name']));
        $this->assertSame(['priced-at', 'priced-between'], $this->oneOf($text['instruction.name']));
        $this->assertSame(['price'], $this->oneOf($sql['instruction.fields.*.name']));
    }

    #[Test]
    public function it_checks_scope_names_against_a_one_of_rule(): void
    {
        $rules = (new SearchScope)->setResource($this->resource(PostResource::class))->buildValidationRules('scope', ['name' => 'popular']);

        $this->assertSame(['publishedSince', 'popular', 'viewedBetween', 'featured', 'reaching'], $this->oneOf($rules['scope.name']));
    }

    #[Test]
    public function it_checks_action_field_names_against_a_one_of_rule(): void
    {
        $request = OperateRequest::create('/api/posts/actions/publish-posts', 'POST')->setContainer($this->app);
        $route = $this->app['router']->getRoutes()->match($request);
        $route->getController();
        $request->setRouteResolver(fn () => $route);

        $this->assertSame(['at', 'notify'], $this->oneOf($request->rules()['fields.*.name']));
    }

    private function products(): Resource
    {
        return $this->resource(ProductResource::class);
    }

    /** @param class-string<Resource> $class */
    private function resource(string $class): Resource
    {
        return RestRequest::create('/', 'POST')->setContainer($this->app)->context()->resource($class);
    }

    /**
     * @param  list<mixed>  $rules
     * @return list<string>
     */
    private function oneOf(array $rules): array
    {
        $oneOf = array_values(array_filter($rules, fn (mixed $rule): bool => $rule instanceof OneOf));

        $this->assertCount(1, $oneOf);

        return $oneOf[0]->values();
    }
}
