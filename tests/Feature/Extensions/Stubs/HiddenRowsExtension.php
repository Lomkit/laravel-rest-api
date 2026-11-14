<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Stubs;

use Lomkit\Rest\Extensions\Contexts\RestrictionContext;
use Lomkit\Rest\Extensions\Extension;
use Lomkit\Rest\Extensions\Kinds\RestrictsQueries;
use Lomkit\Rest\Tests\Fixtures\Archive\Document;
use Lomkit\Rest\Tests\Fixtures\Library\Book;
use Lomkit\Rest\Tests\Fixtures\Library\Label;
use Lomkit\Rest\Tests\Fixtures\Library\Loan;
use Lomkit\Rest\Tests\Fixtures\Library\Shelf;
use Lomkit\Rest\Tests\Fixtures\Publishing\Author;
use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use Lomkit\Rest\Tests\Fixtures\Publishing\Tag;

final class HiddenRowsExtension extends Extension implements RestrictsQueries
{
    private const array COLUMNS = [
        Author::class => 'last_name',
        Post::class => 'title',
        Tag::class => 'name',
        Book::class => 'title',
        Shelf::class => 'name',
        Loan::class => 'borrower',
        Label::class => 'text',
        Document::class => 'title',
    ];

    #[\Override]
    public function restrict(RestrictionContext $context): void
    {
        RestrictionLog::$seen[] = class_basename($context->resource).' '.self::identify($context->user?->getAuthIdentifier());
        RestrictionLog::$users[] = $context->user;
        $column = self::COLUMNS[$context->query->getModel()::class] ?? null;

        if ($column === null) {
            return;
        }

        $context->query->where($context->query->qualifyColumn($column), '!=', 'Hidden');
    }

    private static function identify(mixed $identifier): string
    {
        return is_int($identifier) || is_string($identifier) ? (string) $identifier : 'guest';
    }
}
