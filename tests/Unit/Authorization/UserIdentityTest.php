<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Illuminate\Auth\GenericUser;
use Lomkit\Rest\Authorization\UserIdentity;
use Lomkit\Rest\Tests\Fixtures\Accounts\User;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserIdentityTest extends TestCase
{
    #[Test]
    public function it_names_the_guard_the_user_class_and_the_user_key(): void
    {
        $this->assertSame('web|Illuminate\Auth\GenericUser#7', UserIdentity::of('web', new GenericUser(['id' => 7]))->key());
    }

    #[Test]
    public function it_names_a_guest_by_its_guard(): void
    {
        $this->assertSame('api|guest', UserIdentity::of('api', null)->key());
    }

    #[Test]
    public function it_keeps_two_guards_apart(): void
    {
        $user = new GenericUser(['id' => 7]);

        $this->assertNotSame(UserIdentity::of('web', $user)->key(), UserIdentity::of('api', $user)->key());
    }

    #[Test]
    public function it_keeps_two_user_classes_with_one_key_apart(): void
    {
        $this->assertSame(
            ['web|Illuminate\Auth\GenericUser#7', 'web|Lomkit\Rest\Tests\Fixtures\Accounts\User#7'],
            [UserIdentity::of('web', new GenericUser(['id' => 7]))->key(), UserIdentity::of('web', new User(['id' => 7]))->key()],
        );
    }

    #[Test]
    public function it_encodes_a_user_key_holding_a_separator(): void
    {
        $this->assertSame('web|Illuminate\Auth\GenericUser#a%7Cb', UserIdentity::of('web', new GenericUser(['id' => 'a|b']))->key());
    }
}
