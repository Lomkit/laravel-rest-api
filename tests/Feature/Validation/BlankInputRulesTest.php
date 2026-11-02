<?php

namespace Lomkit\Rest\Tests\Feature\Validation;

use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\Fixtures\Catalog\ProductResource;
use Lomkit\Rest\Tests\PackageTestCase;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\InputSet;
use Lomkit\Rest\Validation\NodeBudget;
use Lomkit\Rest\Validation\ParsedRules;
use Lomkit\Rest\Validation\ValidationContext;
use Lomkit\Rest\Validation\Violations;
use PHPUnit\Framework\Attributes\Test;

class BlankInputRulesTest extends PackageTestCase
{
    #[\Override]
    protected function fixtures(): array
    {
        return [new Catalog];
    }

    #[Test]
    public function it_gives_the_filled_rule_to_a_blank_parameter_whose_field_cannot_take_it(): void
    {
        $context = $this->context();
        $parameters = Cursor::of(['parameters' => ['', ' ', '']], new Violations)->at('parameters');

        $this->inputs()->positional($parameters, $context);

        $this->assertSame([false, false, true], $this->filled($context, ['parameters.0', 'parameters.1', 'parameters.2']));
    }

    #[Test]
    public function it_gives_the_filled_rule_to_a_blank_instruction_field_whose_type_cannot_take_it(): void
    {
        $context = $this->context();
        $fields = Cursor::of(['fields' => [
            ['name' => 'code', 'value' => ''],
            ['name' => 'max', 'value' => ' '],
            ['name' => 'min', 'value' => ''],
        ]], new Violations)->at('fields');

        $this->inputs()->named($fields, $context, 'fields:blank-inputs');

        $this->assertSame([false, false, true], $this->filled($context, ['fields.code', 'fields.max', 'fields.min']));
    }

    #[Test]
    public function it_checks_an_instruction_field_given_as_null_with_its_rules(): void
    {
        $context = $this->context();
        $fields = Cursor::of(['fields' => [['name' => 'level', 'value' => null]]], new Violations)->at('fields');
        $inputs = new InputSet(FieldSet::forInputs([Integer::make('level')->rules('sometimes')], 'fields()'));

        $inputs->named($fields, $context, 'fields:levels');

        $this->assertSame(['integer'], $context->rules->rules()['fields.level'] ?? null);
    }

    private function inputs(): InputSet
    {
        return new InputSet(FieldSet::forInputs([Text::make('code'), Integer::make('max')->nullable(), Integer::make('min')], 'fields()'));
    }

    /**
     * @param  list<string>  $keys
     * @return list<bool>
     */
    private function filled(ValidationContext $context, array $keys): array
    {
        $rules = $context->rules->rules();

        return array_map(fn (string $key): bool => in_array('filled', $rules[$key] ?? [], true), $keys);
    }

    private function context(): ValidationContext
    {
        $rest = RestRequest::create('/', 'POST')
            ->setContainer($this->app)
            ->context();

        return ValidationContext::root($rest->resource(ProductResource::class), new NodeBudget, new ParsedRules);
    }
}
