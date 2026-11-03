<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\UnlistedLabelResource;
use Lomkit\Rest\Tests\Feature\Mutate\Stubs\UnlistedLabelsBookController;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\PackageTestCase;
use PHPUnit\Framework\Attributes\Test;

class ResponseBlockLimitTest extends PackageTestCase
{
    private const array RENAME = ['operation' => 'update', 'key' => 1, 'attributes' => ['title' => 'Persuasion']];

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        Book::query()->create(['id' => 1, 'title' => 'Emma']);

        foreach (range(1, 11) as $number) {
            Label::query()->create(['id' => $number, 'text' => 'Shelf mark '.$number, 'labelable_id' => 1, 'labelable_type' => Book::class]);
        }
    }

    #[Test]
    public function it_limits_an_included_relation_of_each_record_by_default(): void
    {
        $response = $this->postJson('/api/books/mutate', ['mutate' => [self::RENAME], 'response' => [
            'selects' => [['field' => 'title']],
            'includes' => [['relation' => 'labels', 'selects' => [['field' => 'text']]]],
        ]]);

        $marks = array_map(fn (int $number): array => ['text' => 'Shelf mark '.$number], range(11, 2));
        $response->assertOk()->assertJsonPath('records', [['title' => 'Persuasion', 'labels' => $marks]]);
    }

    #[Test]
    public function it_refuses_a_limit_on_an_owner_include_of_the_records_before_writing(): void
    {
        $response = $this->postJson('/api/books/mutate', ['mutate' => [self::RENAME], 'response' => [
            'includes' => [['relation' => 'labels', 'includes' => [['relation' => 'labelable', 'limit' => 10]]]],
        ]]);

        $message = 'The response.includes.0.includes.0.limit field is prohibited: this relation loads at most one record per parent.';
        $response->assertUnprocessable()->assertExactJson(['message' => $message, 'errors' => ['response.includes.0.includes.0.limit' => [$message]]]);
        $this->assertSame('Emma', Book::query()->findOrFail(1)->title);
    }

    #[Test]
    public function it_refuses_a_middle_level_whose_include_limit_its_limits_do_not_hold_before_writing(): void
    {
        Rest::resource('unlisted-labels-books', UnlistedLabelsBookController::class)->register();
        $message = 'The resource ['.UnlistedLabelResource::class.'] sets $includeLimit to 5, which is not one of its limits() [10, 25, 50]. Return 5 from limits(), or set $includeLimit to one of them.';

        $this->assertThrows(
            fn () => $this->withoutExceptionHandling()->postJson('/unlisted-labels-books/mutate', ['mutate' => [self::RENAME], 'response' => [
                'includes' => [['relation' => 'labels.labelable']],
            ]]),
            InvalidResourceException::class,
            $message,
        );
        $this->assertSame('Emma', Book::query()->findOrFail(1)->title);
    }
}
