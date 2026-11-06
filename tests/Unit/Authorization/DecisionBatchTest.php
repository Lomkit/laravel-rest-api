<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use LogicException;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Authorization\Decision;
use Lomkit\Rest\Authorization\DecisionBatch;
use Lomkit\Rest\Authorization\DecisionMemo;
use Lomkit\Rest\Authorization\DecisionStore;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DecisionBatchTest extends TestCase
{
    /** @var list<list<string>> */
    private array $reads = [];

    /** @var list<list<string>> */
    private array $writes = [];

    /** @var list<string> */
    private array $evaluated = [];

    #[Test]
    public function it_reads_a_batch_once_and_writes_what_it_evaluated_once(): void
    {
        [$billing, $support, $sales] = [$this->check(1), $this->check(2), $this->check(3)];
        $batch = new DecisionBatch($this->store([$billing->key() => new Decision(false, 'Billing is private.')]), new DecisionMemo);

        $decisions = $batch->around($this->keyed($billing, $support, $sales), function () use ($batch, $billing, $support, $sales): array {
            $decisions = [...$this->resolve($batch, $billing), ...$this->resolve($batch, $support), ...$this->resolve($batch, $sales)];
            $this->assertSame([], $this->writes);

            return $decisions;
        });

        $this->assertSame([[$billing->key(), $support->key(), $sales->key()]], $this->reads);
        $this->assertSame([$support->key(), $sales->key()], $this->evaluated);
        $this->assertSame([[$support->key(), $sales->key()]], $this->writes);
        $this->assertSame(['Billing is private.', null, null], array_map(fn (Decision $decision): ?string => $decision->message, array_values($decisions)));
    }

    #[Test]
    public function it_writes_once_the_outer_batch_ends(): void
    {
        [$billing, $support] = [$this->check(1), $this->check(2)];
        $batch = new DecisionBatch($this->store(), new DecisionMemo);

        $batch->around($this->keyed($billing), function () use ($batch, $billing, $support): void {
            $batch->around($this->keyed($support), fn (): array => $this->resolve($batch, $support));
            $this->resolve($batch, $billing);
            $this->assertSame([], $this->writes);
        });

        $this->assertSame([[$billing->key()], [$support->key()]], $this->reads);
        $this->assertSame([[$support->key(), $billing->key()]], $this->writes);
    }

    #[Test]
    public function it_writes_the_decisions_made_before_an_exception(): void
    {
        [$billing, $support] = [$this->check(1), $this->check(2)];
        $batch = new DecisionBatch($this->store(), new DecisionMemo);

        try {
            $batch->around($this->keyed($billing, $support), function () use ($batch, $billing): never {
                $this->resolve($batch, $billing);

                throw new LogicException('The page failed.');
            });
        } catch (LogicException) {
        }

        $this->assertSame([[$billing->key()]], $this->writes);
    }

    #[Test]
    public function it_reads_and_writes_a_check_outside_a_batch_at_once(): void
    {
        $billing = $this->check(1);
        $batch = new DecisionBatch($this->store(), new DecisionMemo);

        $this->resolve($batch, $billing);
        $this->resolve($batch, $billing);

        $this->assertSame([[$billing->key()], [$billing->key()]], $this->reads);
        $this->assertSame([[$billing->key()], [$billing->key()]], $this->writes);
    }

    #[Test]
    public function it_leaves_the_checks_on_a_row_that_the_request_wrote_out_of_the_store(): void
    {
        $memo = new DecisionMemo;
        $memo->forget($this->team(1));
        [$billing, $support] = [$this->check(1), $this->check(2)];
        $batch = new DecisionBatch($this->store(), $memo);

        $batch->around($this->keyed($billing, $support), fn (): array => [...$this->resolve($batch, $billing), ...$this->resolve($batch, $support)]);

        $this->assertSame([[$support->key()]], $this->reads);
        $this->assertSame([$billing->key(), $support->key()], $this->evaluated);
        $this->assertSame([[$support->key()]], $this->writes);
    }

    /** @return array<string, Decision> */
    private function resolve(DecisionBatch $batch, Check $check): array
    {
        return $batch->resolve($this->keyed($check), function (Check $check): Decision {
            $this->evaluated[] = $check->key();

            return Decision::allow();
        });
    }

    /** @return array<string, Check> */
    private function keyed(Check ...$checks): array
    {
        return array_combine(array_map(fn (Check $check): string => $check->key(), $checks), $checks);
    }

    /** @param array<string, Decision> $held */
    private function store(array $held = []): DecisionStore
    {
        $test = $this;

        return new class($held, $test) implements DecisionStore
        {
            /** @param array<string, Decision> $held */
            public function __construct(private readonly array $held, private readonly DecisionBatchTest $test) {}

            #[\Override]
            public function many(array $checks): array
            {
                $this->test->read(array_keys($checks));

                return array_intersect_key($this->held, $checks);
            }

            #[\Override]
            public function putMany(array $checks, array $decisions): void
            {
                $this->test->wrote(array_keys($checks));
            }
        };
    }

    /** @param list<string> $keys */
    public function read(array $keys): void
    {
        $this->reads[] = $keys;
    }

    /** @param list<string> $keys */
    public function wrote(array $keys): void
    {
        $this->writes[] = $keys;
    }

    private function check(int $team): Check
    {
        return new Check(new TeamResource, 'view', $this->team($team));
    }

    private function team(int $id): Team
    {
        return (new Team)->newFromBuilder(['id' => $id, 'name' => 'Billing']);
    }
}
