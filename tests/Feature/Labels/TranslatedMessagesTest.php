<?php

namespace Lomkit\Rest\Tests\Feature\Labels;

use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Concerns\AssertsExactErrors;
use Lomkit\Rest\Tests\Feature\Labels\Stubs\LabelledProductController;
use Lomkit\Rest\Tests\Fixtures\Catalog\Catalog;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class TranslatedMessagesTest extends PackageTestCase
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

        $this->app->make('translator')->addJsonPath(__DIR__.'/lang');
        $this->app->setLocale('fr');
        $this->app->make('translator')->addLines(['validation.integer' => 'Le champ :attribute doit être un entier.'], 'fr');
        Rest::resource('labelled-products', LabelledProductController::class)->register();
    }

    #[Test]
    public function it_translates_a_label_given_through_the_translator(): void
    {
        $response = $this->postJson('/labelled-products/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['name' => 'Floor lamp', 'price' => 'x']],
        ]]);

        $this->assertExactErrors($response, ['mutate.0.attributes.price' => ['Le champ prix unitaire doit être un entier.']]);
    }

    #[Test]
    public function it_translates_the_not_writable_text_with_the_package_line(): void
    {
        $response = $this->postJson('/labelled-products/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['id' => 3, 'name' => 'Floor lamp', 'price' => 10]],
        ]]);

        $this->assertExactErrors($response, [
            'mutate.0.attributes' => ["La clé id du champ mutate.0.attributes n'est pas modifiable par l'opération create."],
        ]);
    }

    #[Test]
    public function it_translates_the_relation_text_with_the_package_line(): void
    {
        $response = $this->postJson('/api/products/search', ['search' => ['includes' => [['relation' => 'suppliers']]]]);

        $this->assertExactErrors($response, [
            'search.includes.0.relation' => ["Le champ search.includes.0.relation n'est pas une relation valide ou permise pour cette ressource."],
        ]);
    }
}
