<?php

namespace Lomkit\Rest\Tests\Unit\Console;

use Lomkit\Rest\Console\QuickStart\ExposureReport;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class ExposureReportTest extends TestCase
{
    #[Test]
    public function it_reads_the_json_that_it_writes(): void
    {
        $report = (new ExposureReport)
            ->withAuthenticatedRoute()
            ->withPolicy()
            ->withProblem('POST /api/tags/search is not behind an authentication middleware.')
            ->withSearch('api/v1/posts/search')
            ->withSearch('api/v1/tags/search');

        $read = ExposureReport::fromJson($report->toJson());

        $this->assertEquals($report, $read);
        $this->assertSame('/api/v1/posts/search', $read?->search);
        $this->assertSame(['POST /api/tags/search is not behind an authentication middleware.'], $read->problems);
    }

    #[Test]
    #[DataProvider('invalidReports')]
    public function it_rejects_output_that_is_not_a_report(string $output): void
    {
        $this->assertNull(ExposureReport::fromJson($output));
    }

    /** @return array<string, array{string}> */
    public static function invalidReports(): array
    {
        return [
            'no output' => [''],
            'an error' => ['PHP Fatal error: Allowed memory size exhausted'],
            'a list' => ['[1, 2]'],
            'a missing count' => ['{"policies":1,"problems":[],"search":null}'],
            'a count as text' => ['{"routes":"5","policies":1,"problems":[],"search":null}'],
            'problems as text' => ['{"routes":5,"policies":1,"problems":"none","search":null}'],
            'a search route as a number' => ['{"routes":5,"policies":1,"problems":[],"search":5}'],
        ];
    }
}
