<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Authorization\Changes;
use Lomkit\Rest\Fields\DateTime;
use Lomkit\Rest\Fields\ID;
use Lomkit\Rest\Fields\Integer;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Relations\BelongsTo;
use Lomkit\Rest\Tests\Fixtures\Catalog\Product;

class VersionedProductResource extends Resource
{
    public static string $model = Product::class;

    /** @var list<string> */
    public static array $hooks = [];

    #[\Override]
    public function fields(): array
    {
        return [
            ID::make('id'),
            Integer::make('category_id'),
            Text::make('name'),
            Integer::make('price'),
            DateTime::make('updated_at')->readonly(),
        ];
    }

    #[\Override]
    public function relations(): array
    {
        return [BelongsTo::make('category', VersionedCategoryResource::class)];
    }

    #[\Override]
    public function versioned(): bool
    {
        return true;
    }

    /** @param list<Model|Changes> $arguments */
    #[\Override]
    public function authorizedTo(string $ability, Model|string $model, array $arguments = []): Response
    {
        if ($ability === 'update' && $model instanceof Model && $model->getAttribute('name') === 'Sealed lamp') {
            return Response::deny('Sealed products are kept.');
        }

        return parent::authorizedTo($ability, $model, $arguments);
    }

    #[\Override]
    public function mutatingAll(array $entries): void
    {
        self::$hooks[] = 'mutatingAll';
    }

    #[\Override]
    public function mutatedAll(array $operations): void
    {
        self::$hooks[] = 'mutatedAll';
    }
}
