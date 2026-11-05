<?php

namespace Lomkit\Rest\Tests\Feature\Serialization\Stubs;

use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\ReadableAuthorPolicy;

#[Table('authors')]
#[UsePolicy(ReadableAuthorPolicy::class)]
class TrackedAuthor extends Author
{
    /** @var list<string> */
    public static array $read = [];

    /** @var list<string> */
    protected $appends = ['theme'];

    /** @return Attribute<string, never> */
    protected function lastName(): Attribute
    {
        return Attribute::get(function (string $value): string {
            self::$read[] = 'last_name';

            return $value;
        });
    }

    /** @return Attribute<string, never> */
    protected function theme(): Attribute
    {
        return Attribute::get(function (): string {
            self::$read[] = 'theme';

            return $this->getAttribute('settings')['theme'] ?? 'light';
        });
    }
}
