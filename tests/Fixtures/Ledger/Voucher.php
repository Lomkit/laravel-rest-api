<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Engines\DatabaseEngine;
use Laravel\Scout\Engines\TypesenseEngine;
use Laravel\Scout\Searchable;

#[Table('vouchers')]
#[Unguarded]
#[UsePolicy(OpenLedgerPolicy::class)]
class Voucher extends Model
{
    use Searchable;
    use SoftDeletes;

    /** @return HasMany<VoucherLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(VoucherLine::class, 'voucher_id');
    }

    #[Scope]
    protected function cheap(Builder $query): void
    {
        $query->where('price', '<', 10);
    }

    public function getScoutKey(): mixed
    {
        return $this->code;
    }

    public function getScoutKeyName(): string
    {
        return 'code';
    }

    public function getScoutKeyType(): string
    {
        return 'string';
    }

    /** @return array<string, mixed> */
    public function toSearchableArray(): array
    {
        $engine = $this->searchableUsing();

        if ($engine instanceof DatabaseEngine) {
            return ['code' => $this->code, 'title' => $this->title, 'team' => $this->team];
        }

        $document = $engine instanceof TypesenseEngine ? ['id' => (string) $this->getScoutKey()] : [];

        return [
            ...$document,
            'code' => $this->code,
            'number' => $this->number,
            'team' => $this->team,
            'title' => $this->title,
            'price' => $this->price,
            'active' => $this->active,
        ];
    }

    /** @return array<string, string> */
    #[\Override]
    protected function casts(): array
    {
        return ['number' => 'integer', 'price' => 'float', 'active' => 'boolean'];
    }
}
