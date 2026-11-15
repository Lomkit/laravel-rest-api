<?php

namespace Lomkit\Rest\Tests\Feature\Extensions\Meta;

use ArrayObject;
use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Lomkit\Rest\Authorization\ActingUser;
use Lomkit\Rest\Exceptions\InvalidMetaExtensionException;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Http\Requests\RestRequest;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Tests\Feature\Authorization\HelpdeskTestCase;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MetaLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MetaTicketController;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MetaTicketResource;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\QuotaDetailsMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RecordingReadObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RegionDetailsMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\UnencodableMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ValueDetailsMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WriteLog;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\Ticket;
use Lomkit\Rest\Tests\Fixtures\Helpdesk\TicketPolicy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

final class DetailsMetaExtensionsTest extends HelpdeskTestCase
{
    private const string META = '"meta":{"plan":"pro","quota":{"plan":"pro","user":1},"region":{}}';

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        MetaLog::reset();
        WriteLog::reset();
        Rest::resource('meta-tickets', MetaTicketController::class)->register();
        config(['rest.extensions' => ['quota' => QuotaDetailsMeta::class, 'region' => RegionDetailsMeta::class]]);
    }

    #[\Override]
    protected function tearDown(): void
    {
        MetaLog::reset();
        WriteLog::reset();

        parent::tearDown();
    }

    #[Test]
    public function it_adds_the_meta_of_each_extension_under_its_name_in_details(): void
    {
        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));

        $response = $this->getJson('/meta-tickets');

        $response->assertOk()->assertExactJson(['data' => [
            'fields' => [
                ['name' => 'id', 'label' => null, 'type' => 'id', 'nullable' => false],
                ['name' => 'team_id', 'label' => null, 'type' => 'integer', 'nullable' => false],
                ['name' => 'title', 'label' => null, 'type' => 'text', 'nullable' => false],
                ['name' => 'status', 'label' => null, 'type' => 'text', 'nullable' => false],
            ],
            'limits' => [10, 25, 50],
            'meta' => ['plan' => 'pro', 'quota' => ['plan' => 'pro', 'user' => 1], 'region' => []],
        ]]);
        $this->assertStringContainsString(self::META, (string) $response->getContent());
    }

    /** @return array<string, array{Authenticatable|null, int|null}> */
    public static function users(): array
    {
        return [
            'a guest' => [null, null],
            'the lead' => [new GenericUser(['id' => TicketPolicy::LEAD]), 1],
            'another user' => [new GenericUser(['id' => 7]), 7],
        ];
    }

    #[Test]
    #[DataProvider('users')]
    public function it_answers_the_details_meta_for_the_current_user(?Authenticatable $user, ?int $id): void
    {
        if ($user !== null) {
            $this->actingAs($user);
        }

        $this->getJson('/meta-tickets')->assertOk()->assertJsonPath('data.meta.quota', ['plan' => 'pro', 'user' => $id]);

        $this->assertCount(1, MetaLog::$details);
        $this->assertInstanceOf(MetaTicketResource::class, MetaLog::$details[0]->resource);
        $this->assertSame($user, MetaLog::$details[0]->user);
    }

    /** @return array<string, array{array<string, class-string>}> */
    public static function orders(): array
    {
        return [
            'quota first' => [['quota' => QuotaDetailsMeta::class, 'region' => RegionDetailsMeta::class]],
            'region first' => [['region' => RegionDetailsMeta::class, 'quota' => QuotaDetailsMeta::class]],
        ];
    }

    /** @param array<string, class-string> $extensions */
    #[Test]
    #[DataProvider('orders')]
    public function it_orders_the_details_meta_by_name_whatever_the_config_order(array $extensions): void
    {
        config(['rest.extensions' => $extensions]);
        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));

        $content = (string) $this->getJson('/meta-tickets')->assertOk()->getContent();

        $this->assertStringContainsString(self::META, $content);
    }

    #[Test]
    public function it_refuses_a_details_meta_key_of_the_resource_that_an_extension_takes(): void
    {
        MetaLog::$detailsMeta = ['quota' => 1];
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetaExtensionException::keyTaken('quota', QuotaDetailsMeta::class, MetaTicketResource::class, 'detailsMeta'));

        $this->getJson('/meta-tickets');
    }

    #[Test]
    public function it_refuses_a_details_value_that_json_cannot_encode(): void
    {
        MetaLog::$value = ['list' => [new ArrayObject]];
        config(['rest.extensions' => ['value' => ValueDetailsMeta::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetaExtensionException::notEncodable('value', ValueDetailsMeta::class, MetaTicketResource::class, 'detailsMeta', 'value.value.list.0', 'ArrayObject'));

        $this->getJson('/meta-tickets');
    }

    #[Test]
    public function it_refuses_a_model_in_the_details_meta(): void
    {
        MetaLog::$value = ['list' => [new Ticket(['title' => 'Printer jam'])]];
        config(['rest.extensions' => ['value' => ValueDetailsMeta::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetaExtensionException::notEncodable('value', ValueDetailsMeta::class, MetaTicketResource::class, 'detailsMeta', 'value.value.list.0', Ticket::class));

        $this->getJson('/meta-tickets');
    }

    /** @return array<string, array{mixed, string}> */
    public static function notJson(): array
    {
        return UnencodableMeta::cases();
    }

    #[Test]
    #[DataProvider('notJson')]
    public function it_refuses_a_details_value_that_json_refuses_naming_the_extension(mixed $value, string $reason): void
    {
        MetaLog::$value = $value;
        config(['rest.extensions' => ['value' => ValueDetailsMeta::class]]);
        $this->withoutExceptionHandling();

        $this->expectExceptionObject(InvalidMetaExtensionException::notJson('value', ValueDetailsMeta::class, MetaTicketResource::class, 'detailsMeta', $reason));

        $this->getJson('/meta-tickets');
    }

    #[Test]
    #[DataProvider('notJson')]
    public function it_refuses_a_value_that_json_refuses_in_the_json_of_a_resource(mixed $value, string $reason): void
    {
        MetaLog::$value = $value;
        config(['rest.extensions' => ['value' => ValueDetailsMeta::class]]);
        $context = new RestContext(RestRequest::create('/meta-tickets', 'GET'), $this->app, ActingUser::of('web', null));

        $this->expectExceptionObject(InvalidMetaExtensionException::notJson('value', ValueDetailsMeta::class, MetaTicketResource::class, 'detailsMeta', $reason));

        json_encode($context->resource(MetaTicketResource::class));
    }

    #[Test]
    public function it_adds_the_extension_meta_to_the_json_of_a_resource(): void
    {
        $guest = new RestContext(RestRequest::create('/meta-tickets', 'GET'), $this->app, ActingUser::of('web', null));
        $user = new RestContext(RestRequest::create('/meta-tickets', 'GET'), $this->app, ActingUser::of('web', new GenericUser(['id' => 7])));

        $this->assertStringContainsString('"meta":{"plan":"pro","quota":{"plan":"pro","user":null},"region":{}}', (string) json_encode($guest->resource(MetaTicketResource::class)));
        $this->assertStringContainsString('"meta":{"plan":"pro","quota":{"plan":"pro","user":7},"region":{}}', (string) json_encode($user->resource(MetaTicketResource::class)));
    }

    #[Test]
    public function it_hands_the_extension_meta_to_read_observers(): void
    {
        config(['rest.extensions' => ['quota' => QuotaDetailsMeta::class, 'reads' => RecordingReadObserver::class]]);
        $this->actingAs(new GenericUser(['id' => TicketPolicy::LEAD]));

        $this->getJson('/meta-tickets')->assertOk();

        $this->assertSame(['plan' => 'pro', 'quota' => ['plan' => 'pro', 'user' => 1]], WriteLog::$reads[0]->details['meta'] ?? null);
    }
}
