<?php

namespace Lomkit\Rest\Tests\Unit\Config;

use Illuminate\Config\Repository;
use Illuminate\Support\Env;
use Lomkit\Rest\Config\RestConfig;
use Lomkit\Rest\Exceptions\InvalidConfigException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RestConfigTest extends TestCase
{
    private const array VARIABLES = ['REST_AUTHORIZATIONS_STRICT', 'REST_AUTHORIZATIONS_CACHE_ENABLED', 'REST_AUTHORIZATIONS_CACHE_STORE'];

    #[\Override]
    protected function tearDown(): void
    {
        foreach (self::VARIABLES as $variable) {
            Env::getRepository()->clear($variable);
        }

        parent::tearDown();
    }

    #[Test]
    public function it_reads_the_package_config(): void
    {
        $config = RestConfig::from($this->packageConfig());

        $this->assertFalse($config->strict);
        $this->assertTrue($config->cacheEnabled);
        $this->assertNull($config->cacheStore);
        $this->assertSame('gates', $config->gatesKey);
        $this->assertSame('@type', $config->morphTypeKey);
        $this->assertTrue($config->selectColumns);
        $this->assertSame([
            'authorized_to_view' => 'authorized_to_view',
            'authorized_to_create' => 'authorized_to_create',
            'authorized_to_update' => 'authorized_to_update',
            'authorized_to_delete' => 'authorized_to_delete',
            'authorized_to_restore' => 'authorized_to_restore',
            'authorized_to_force_delete' => 'authorized_to_force_delete',
        ], $config->gateNames);
    }

    #[Test]
    public function it_turns_strict_mode_on_from_the_environment(): void
    {
        Env::getRepository()->set('REST_AUTHORIZATIONS_STRICT', 'true');

        $config = RestConfig::from($this->packageConfig());

        $this->assertTrue($config->strict);
        $this->assertTrue($config->cacheEnabled);
    }

    #[Test]
    public function it_turns_the_cache_off_from_the_environment(): void
    {
        Env::getRepository()->set('REST_AUTHORIZATIONS_CACHE_ENABLED', '0');

        $config = RestConfig::from($this->packageConfig());

        $this->assertFalse($config->cacheEnabled);
        $this->assertNull($config->cacheStore);
    }

    #[Test]
    public function it_names_the_cache_store_from_the_environment(): void
    {
        Env::getRepository()->set('REST_AUTHORIZATIONS_CACHE_STORE', 'redis');

        $config = RestConfig::from($this->packageConfig());

        $this->assertSame('redis', $config->cacheStore);
        $this->assertTrue($config->cacheEnabled);
    }

    #[Test]
    public function it_names_an_environment_value_that_is_not_a_boolean(): void
    {
        Env::getRepository()->set('REST_AUTHORIZATIONS_STRICT', 'on');

        $this->expectExceptionObject(new InvalidConfigException('The [rest.authorizations.strict] option must be a boolean.'));

        RestConfig::from($this->packageConfig());
    }

    #[Test]
    #[DataProvider('wrongTypes')]
    public function it_names_the_key_of_each_value_of_the_wrong_type(string $key, mixed $value, string $message): void
    {
        $config = $this->packageConfig();
        $config->set($key, $value);

        $this->expectExceptionObject(new InvalidConfigException($message));

        RestConfig::from($config);
    }

    #[Test]
    #[DataProvider('requiredGateNames')]
    public function it_names_a_gate_name_missing_from_the_map(string $name, string $message): void
    {
        $config = $this->packageConfig();
        $names = $config->get('rest.gates.names');
        $this->assertIsArray($names);
        unset($names[$name]);
        $config->set('rest.gates.names', $names);

        $this->expectExceptionObject(new InvalidConfigException($message));

        RestConfig::from($config);
    }

    /** @return array<string, array{string, mixed, string}> */
    public static function wrongTypes(): array
    {
        return [
            'strict mode' => ['rest.authorizations.strict', 'on', 'The [rest.authorizations.strict] option must be a boolean.'],
            'the cache switch' => ['rest.authorizations.cache.enabled', null, 'The [rest.authorizations.cache.enabled] option must be a boolean.'],
            'the cache store' => ['rest.authorizations.cache.store', '', 'The [rest.authorizations.cache.store] option must be null or a non-empty string.'],
            'the gates key' => ['rest.gates.key', '', 'The [rest.gates.key] option must be a non-empty string.'],
            'the gate names' => ['rest.gates.names', ['can_view'], 'The [rest.gates.names] option must be a map of strings.'],
            'one gate name' => ['rest.gates.names.authorized_to_delete', false, 'The [rest.gates.names.authorized_to_delete] option must be a string.'],
            'the morph type key' => ['rest.morph.type_key', '', 'The [rest.morph.type_key] option must be a non-empty string.'],
            'the column selection' => ['rest.search.select_columns', 'yes', 'The [rest.search.select_columns] option must be a boolean.'],
        ];
    }

    /** @return array<string, array{string, string}> */
    public static function requiredGateNames(): array
    {
        return [
            'view' => ['authorized_to_view', 'The [rest.gates.names.authorized_to_view] option must be a string.'],
            'create' => ['authorized_to_create', 'The [rest.gates.names.authorized_to_create] option must be a string.'],
            'update' => ['authorized_to_update', 'The [rest.gates.names.authorized_to_update] option must be a string.'],
            'delete' => ['authorized_to_delete', 'The [rest.gates.names.authorized_to_delete] option must be a string.'],
            'restore' => ['authorized_to_restore', 'The [rest.gates.names.authorized_to_restore] option must be a string.'],
            'force delete' => ['authorized_to_force_delete', 'The [rest.gates.names.authorized_to_force_delete] option must be a string.'],
        ];
    }

    private function packageConfig(): Repository
    {
        $config = require __DIR__.'/../../../config/rest.php';

        $this->assertIsArray($config);

        return new Repository(['rest' => $config]);
    }
}
