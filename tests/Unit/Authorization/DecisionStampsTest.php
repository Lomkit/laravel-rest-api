<?php

namespace Lomkit\Rest\Tests\Unit\Authorization;

use Illuminate\Cache\ArrayStore;
use Illuminate\Cache\Repository;
use Illuminate\Support\Str;
use Lomkit\Rest\Authorization\DecisionStamps;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class DecisionStampsTest extends TestCase
{
    private const string USER = 'Illuminate\Auth\GenericUser#7';

    private Repository $cache;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->cache = new Repository(new ArrayStore);
        Str::createRandomStringsUsingSequence(['every-1', 'user-1', 'user-2', 'every-2']);
    }

    #[\Override]
    protected function tearDown(): void
    {
        Str::createRandomStringsNormally();

        parent::tearDown();
    }

    #[Test]
    public function it_creates_the_missing_stamps_and_reads_them_once_per_object(): void
    {
        $stamps = new DecisionStamps(self::USER);

        $first = $stamps->read($this->cache);
        $stored = $this->cache->many(['rest.authorizations.stamp', 'rest.authorizations.stamp.'.hash('sha256', self::USER)]);
        $this->cache->flush();

        $this->assertSame(['every-1|user-1', 'every-1|user-1'], [$first, $stamps->read($this->cache)]);
        $this->assertSame(['rest.authorizations.stamp' => 'every-1', 'rest.authorizations.stamp.'.hash('sha256', self::USER) => 'user-1'], $stored);
    }

    #[Test]
    public function it_reads_the_stamps_that_the_store_holds(): void
    {
        $this->cache->forever('rest.authorizations.stamp', 'every-0');
        $this->cache->forever('rest.authorizations.stamp.'.hash('sha256', self::USER), 'user-0');

        $this->assertSame('every-0|user-0', (new DecisionStamps(self::USER))->read($this->cache));
    }

    #[Test]
    public function it_reads_only_the_stamp_of_every_user_for_a_guest(): void
    {
        $this->assertSame('every-1', (new DecisionStamps(null))->read($this->cache));
        $this->assertSame(['rest.authorizations.stamp'], array_keys($this->cache->getStore()->all()));
        $this->assertSame('every-1', $this->cache->get('rest.authorizations.stamp'));
    }

    #[Test]
    public function it_renews_the_stamp_of_one_user_or_the_stamp_of_every_user(): void
    {
        (new DecisionStamps(self::USER))->read($this->cache);

        DecisionStamps::renew($this->cache, self::USER);
        $user = (new DecisionStamps(self::USER))->read($this->cache);
        DecisionStamps::renew($this->cache, null);
        $every = (new DecisionStamps(self::USER))->read($this->cache);

        $this->assertSame(['every-1|user-2', 'every-2|user-2'], [$user, $every]);
    }
}
