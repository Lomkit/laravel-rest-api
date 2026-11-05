<?php

namespace Lomkit\Rest\Tests\Feature\Mutate;

use Lomkit\Rest\Tests\Feature\Mutate\Characterization\Stubs\TracingPolicy;
use Lomkit\Rest\Tests\Feature\Mutate\Characterization\WriteTraceTestCase;
use PHPUnit\Framework\Attributes\Test;

class NamedRowsTest extends WriteTraceTestCase
{
    #[Test]
    public function it_checks_a_row_that_three_entries_name_as_it_checks_three_reads_of_it(): void
    {
        $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 102, 'attributes' => ['title' => 'Emma Woodhouse']],
            ['operation' => 'update', 'key' => 102, 'attributes' => ['title' => 'Emma Woodhouse']],
            ['operation' => 'update', 'key' => 102, 'attributes' => ['title' => 'Emma W.']],
        ]])->assertOk();

        $this->assertSame(
            ['policy update Book(Emma)', 'saved Book(Emma Woodhouse)', 'policy update Book(Emma Woodhouse)', 'saved Book(Emma Woodhouse)', 'saved Book(Emma W.)'],
            array_values(array_filter(TracingPolicy::$trace, fn (string $line): bool => ! str_starts_with($line, 'sql '))),
        );
        $this->assertSame(['Book(Dune) [Shelf(Classics)]', 'Book(Emma W.) []'], self::rows('books'));
    }

    #[Test]
    public function it_writes_a_row_that_two_resources_name_as_one_row(): void
    {
        $this->postJson('/api/uncached-tickets/mutate', ['mutate' => [
            ['operation' => 'update', 'key' => 101, 'attributes' => ['title' => 'Paper jam']],
            ['operation' => 'update', 'key' => 102, 'relations' => ['team' => [
                'operation' => 'update',
                'key' => 101,
                'relations' => ['tickets' => [['operation' => 'update', 'key' => 101, 'attributes' => ['title' => 'Printer jam']]]],
            ]]],
        ]])->assertOk();

        $this->assertSame(['Ticket(Password reset) open [] []', 'Ticket(Printer jam) open [Tag(Hardware) 1] []'], self::rows('tickets'));
    }

    #[Test]
    public function it_refuses_the_key_of_a_row_that_the_same_request_creates(): void
    {
        $response = $this->postJson('/api/books/mutate', ['mutate' => [
            ['operation' => 'create', 'attributes' => ['title' => 'Solaris']],
            ['operation' => 'update', 'key' => 103, 'attributes' => ['title' => 'Solaris (1961)']],
        ]]);

        $response->assertUnprocessable();
        $response->assertJsonPath('errors', ['mutate.1.key' => ['The selected mutate.1.key is invalid.']]);
        $this->assertSame(['Book(Dune) [Shelf(Classics)]', 'Book(Emma) []'], self::rows('books'));
    }
}
