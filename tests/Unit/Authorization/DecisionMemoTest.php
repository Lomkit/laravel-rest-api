<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Authorization\Decision;
use Lomkit\Rest\Authorization\DecisionMemo;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Team;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TeamResource;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DecisionMemoTest extends TestCase
{
    #[Test]
    public function it_resolves_a_key_once(): void
    {
        $memo = new DecisionMemo;
        $check = new Check(new TeamResource, 'view', $this->team(1));
        $resolutions = 0;
        $resolver = function () use (&$resolutions): Decision {
            $resolutions++;

            return new Decision(false, 'Billing is private.');
        };

        $first = $memo->remember($check->key(), $check, $resolver);
        $second = $memo->remember($check->key(), $check, $resolver);

        $this->assertSame($first, $second);
        $this->assertSame(1, $resolutions);
        $this->assertTrue($memo->has($check->key()));
    }

    #[Test]
    public function it_forgets_the_decisions_whose_subject_or_related_row_is_the_model(): void
    {
        $memo = new DecisionMemo;
        $billing = new Check(new TeamResource, 'view', $this->team(1));
        $support = new Check(new TeamResource, 'view', $this->team(2));
        $link = new Check(new TeamResource, 'attachTeam', $this->team(2), related: [$this->team(1)]);
        foreach ([$billing, $support, $link] as $check) {
            $memo->remember($check->key(), $check, fn (): Decision => Decision::allow());
        }

        $memo->forget($this->team(1));

        $this->assertSame([false, true, false], [$memo->has($billing->key()), $memo->has($support->key()), $memo->has($link->key())]);
    }

    #[Test]
    public function it_tells_which_checks_involve_a_forgotten_model(): void
    {
        $memo = new DecisionMemo;

        $memo->forget($this->team(1));

        $this->assertTrue($memo->involvesForgotten(new Check(new TeamResource, 'update', $this->team(1))));
        $this->assertTrue($memo->involvesForgotten(new Check(new TeamResource, 'attachTeam', $this->team(2), related: [$this->team(1)])));
        $this->assertFalse($memo->involvesForgotten(new Check(new TeamResource, 'update', $this->team(2))));
        $this->assertFalse($memo->involvesForgotten(Check::onClass(new TeamResource, 'viewAny')));
    }

    #[Test]
    public function it_forgets_an_unsaved_model_only_as_that_instance(): void
    {
        $memo = new DecisionMemo;
        $first = new Check(new TeamResource, 'create', $team = new Team);
        $second = new Check(new TeamResource, 'create', new Team);
        $memo->remember($first->key(), $first, fn (): Decision => Decision::allow());
        $memo->remember($second->key(), $second, fn (): Decision => Decision::allow());

        $memo->forget($team);

        $this->assertSame([false, true], [$memo->has($first->key()), $memo->has($second->key())]);
    }

    #[Test]
    public function it_forgets_the_creation_of_a_model_once_it_is_saved(): void
    {
        $memo = new DecisionMemo;
        $team = new Team;
        $creation = new Check(new TeamResource, 'create', $team);
        $key = $creation->key();
        $memo->remember($key, $creation, fn (): Decision => Decision::allow());
        $team->forceFill(['id' => 9])->exists = true;
        $update = new Check(new TeamResource, 'update', $team);
        $memo->remember($update->key(), $update, fn (): Decision => Decision::allow());

        $memo->forget($team);
        $memo->forget($team);

        $this->assertSame([false, false], [$memo->has($key), $memo->has($update->key())]);
    }

    private function team(int $id): Team
    {
        return (new Team)->newFromBuilder(['id' => $id, 'name' => 'Billing']);
    }
}
