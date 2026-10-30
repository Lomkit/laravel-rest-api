<?php

namespace Lomkit\Rest\Tests\Unit\Console;

use Illuminate\Config\Repository;
use Lomkit\Rest\Console\Generation\UserModel;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class UserModelTest extends TestCase
{
    /** @param  array<string, mixed>  $auth */
    #[Test]
    #[DataProvider('configurations')]
    public function it_reads_the_user_model_of_the_default_guard(array $auth, string $expected): void
    {
        $this->assertSame($expected, UserModel::configured(new Repository(['auth' => $auth]), 'MyApp\\'));
    }

    /** @return array<string, array{array<string, mixed>, string}> */
    public static function configurations(): array
    {
        return [
            'the provider of the default guard' => [[
                'defaults' => ['guard' => 'staff'],
                'guards' => ['web' => ['provider' => 'users'], 'staff' => ['provider' => 'members']],
                'providers' => ['users' => ['model' => 'MyApp\Models\User'], 'members' => ['model' => '\MyApp\Models\Member']],
            ], 'MyApp\Models\Member'],
            'the users provider when the guard is not defined' => [[
                'defaults' => ['guard' => 'missing'],
                'providers' => ['users' => ['model' => 'MyApp\Models\Account']],
            ], 'MyApp\Models\Account'],
            'the conventional model when the provider has none' => [[
                'defaults' => ['guard' => 'web'],
                'guards' => ['web' => ['provider' => 'users']],
                'providers' => ['users' => ['driver' => 'database']],
            ], 'MyApp\Models\User'],
        ];
    }
}
