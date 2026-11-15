<?php

namespace Lomkit\Rest\Config;

use Illuminate\Contracts\Config\Repository;

final readonly class RestConfig
{
    public const string EXTENSIONS = 'rest.extensions';

    public const string DISABLED_EXTENSIONS = 'rest.disabled_extensions';

    private const array GATE_NAMES = [
        'authorized_to_view',
        'authorized_to_create',
        'authorized_to_update',
        'authorized_to_delete',
        'authorized_to_restore',
        'authorized_to_force_delete',
    ];

    /**
     * @param  array<string, string>  $gateNames
     * @param  list<mixed>  $extensions
     * @param  list<mixed>  $disabledExtensions
     */
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
        public array $extensions,
        public array $disabledExtensions,
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
            extensions: self::extensionClasses($config, self::EXTENSIONS),
            disabledExtensions: self::extensionClasses($config, self::DISABLED_EXTENSIONS),
        );
    }

    /** @return list<mixed> */
    public static function extensionClasses(Repository $config, string $key): array
    {
        return ConfigValue::of($config, $key, [])->list('extension class names');
    }
}
