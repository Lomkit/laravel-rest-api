<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Checks;

use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Database\Events\TransactionBeginning;
use Illuminate\Database\Events\TransactionCommitted;
use Illuminate\Database\Events\TransactionRolledBack;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Testing\TestResponse;
use Lomkit\Rest\Exceptions\DenialsRenderer;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\WriteTraceTestCase;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\ChecksBookResource;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\ChecksLabelResource;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\ChecksLoanResource;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\ChecksPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Checks\Stubs\ChecksShelfResource;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\BookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\LabelResource;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\LoanResource;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\Fixtures\Library\ShelfResource;
use Orchestra\Testbench\Attributes\WithConfig;

#[WithConfig('app.debug', false)]
abstract class WriteChecksTestCase extends WriteTraceTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        ChecksPolicy::$refused = [];

        foreach ([Book::class, Loan::class, Label::class, Shelf::class] as $model) {
            Gate::policy($model, ChecksPolicy::class);
        }

        $this->app?->bind(BookResource::class, ChecksBookResource::class);
        $this->app?->bind(LoanResource::class, ChecksLoanResource::class);
        $this->app?->bind(LabelResource::class, ChecksLabelResource::class);
        $this->app?->bind(ShelfResource::class, ChecksShelfResource::class);
        $this->app?->make(ExceptionHandler::class)->renderable(new DenialsRenderer);

        Shelf::query()->insert([['id' => 103, 'name' => 'Drama'], ['id' => 104, 'name' => 'Hidden']]);
        DB::table('book_shelf')->insert([['book_id' => 102, 'shelf_id' => 102], ['book_id' => 102, 'shelf_id' => 103], ['book_id' => 102, 'shelf_id' => 104]]);

        Event::listen(TransactionBeginning::class, fn (): string => TracingPolicy::$trace[] = 'begin');
        Event::listen(TransactionCommitted::class, fn (): string => TracingPolicy::$trace[] = 'commit');
        Event::listen(TransactionRolledBack::class, fn (): string => TracingPolicy::$trace[] = 'rollback');
        TracingPolicy::$trace = [];
    }

    /** @param list<array<string, mixed>> $mutate */
    protected function mutate(string $endpoint, array $mutate): TestResponse
    {
        return $this->postJson($endpoint, ['mutate' => $mutate]);
    }

    /** @return list<string> */
    protected static function writes(): array
    {
        return array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => preg_match('/^(sql (insert|update|delete)|saved|hook)/', $line) === 1));
    }
}
