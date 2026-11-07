<?php

namespace Lomkit\Rest\Config;

use Illuminate\Contracts\Config\Repository;

final readonly class RestConfig
{
    private const array GATE_NAMES = [
        'authorized_to_view',
        'authorized_to_create',
        'authorized_to_update',
        'authorized_to_delete',
        'authorized_to_restore',
        'authorized_to_force_delete',
    ];

    /** @param array<string, string> $gateNames */
    public function __construct(
        public bool $strict,
        public bool $cacheEnabled,
        public ?string $cacheStore,
        public string $gatesKey,
        public array $gateNames,
        public string $morphTypeKey,
        public bool $selectColumns,
        public int $transactionAttempts,
        public string $moreKey,
    ) {}

    public static function from(Repository $config): self
    {
        return new self(
            strict: ConfigValue::of($config, 'rest.authorizations.strict')->boolean(),
            cacheEnabled: ConfigValue::of($config, 'rest.authorizations.cache.enabled')->boolean(),
            cacheStore: ConfigValue::of($config, 'rest.authorizations.cache.store')->nullableString(),
            gatesKey: ConfigValue::of($config, 'rest.gates.key')->string(),
            gateNames: ConfigValue::of($config, 'rest.gates.names')->strings(self::GATE_NAMES),
            morphTypeKey: ConfigValue::of($config, 'rest.morph.type_key')->string(),
            selectColumns: ConfigValue::of($config, 'rest.search.select_columns')->boolean(),
            transactionAttempts: ConfigValue::of($config, 'rest.transactions.attempts')->integer(1),
            moreKey: ConfigValue::of($config, 'rest.search.more_key')->string(),
        );
    }
}
