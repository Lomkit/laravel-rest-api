<?php

namespace Lomkit\Rest\Tests\Fixtures\Catalog;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

#[Unguarded]
#[UseFactory(ProductFactory::class)]
#[UsePolicy(OpenCatalogPolicy::class)]
class Product extends Model
{
    use HasFactory;
    use Searchable;

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
