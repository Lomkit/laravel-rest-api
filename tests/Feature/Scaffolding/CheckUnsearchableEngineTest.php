<?php

namespace Lomkit\Rest\Tests\Feature\Scaffolding;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Exceptions\InvalidTextSearchException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\HiddenRowsExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictionLog;
use Lomkit\Rest\Tests\Feature\Scaffolding\Stubs\IndexedBookController;
use Lomkit\Rest\Tests\Feature\Scaffolding\Stubs\IndexedBookResource;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Library;
use Lomkit\Rest\Tests\ScaffoldingTestCase;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;
use PHPUnit\Framework\Attributes\Test;
use Symfony\Component\Console\Output\BufferedOutput;

#[WithConfig('auth.guards.sanctum', ['driver' => 'session', 'provider' => 'users'])]
final class CheckUnsearchableEngineTest extends ScaffoldingTestCase
{
    #[Before]
    #[After]
    public function resetTheLog(): void
    {
        RestrictionLog::reset();
    }

    #[\Override]
    protected function fixtures(): array
    {
        return [new Library];
    }

    #[Test]
    public function it_reports_a_restriction_on_an_engine_search_that_cannot_resolve_its_engine(): void
    {
        Route::prefix('api')->group(fn () => Rest::resource('indexed-books', IndexedBookController::class)->middleware('auth:sanctum')->register());
        config(['rest.extensions' => ['hidden' => HiddenRowsExtension::class]]);
        $reason = InvalidTextSearchException::unsearchableModel(IndexedBookResource::class, Book::class)->getMessage();

        $exitCode = Artisan::call('rest:check', ['controllers' => [IndexedBookController::class], '--json' => true], $output = new BufferedOutput);

        $this->assertSame(1, $exitCode);
        $this->assertSame(
            [IndexedBookResource::class.': the [hidden] extension '.HiddenRowsExtension::class." restricts its rows in SQL but does not implement RestrictsEngineQueries, and rest:check cannot resolve the text search or the Scout engine of the resource ({$reason}). A text search of ".IndexedBookResource::class.' is refused on an engine that does not apply SQL restrictions.'],
            json_decode($output->fetch(), true)['rows']['problems'],
        );
    }
}
