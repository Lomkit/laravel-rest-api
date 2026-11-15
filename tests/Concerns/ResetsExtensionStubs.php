<?php

namespace Lomkit\Rest\Tests\Concerns;

use Lomkit\Rest\Tests\Feature\Events\Stubs\EventLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\AugmentationLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ChangingReadObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ClausedExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\DeclaredIndexExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\EngineLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstCacheContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstGates;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstNarrowing;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\FirstResponses;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MaskingFields;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\MetaLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OtherMaskingFields;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\OtherStampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\ReadersExtension;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RecordingReadObserver;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RegionStageMeta;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RestrictionLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RoutedStampNamedLimits;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\RowCheckLog;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondCacheContext;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondGates;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondNarrowing;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SecondResponses;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\SqlForeignEngineProfiles;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampRoutes;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\StampsRequests;
use Lomkit\Rest\Tests\Feature\Extensions\Stubs\WriteLog;
use Lomkit\Rest\Tests\Fixtures\Extensions\ExtensionLog;
use PHPUnit\Framework\Attributes\After;
use PHPUnit\Framework\Attributes\Before;

trait ResetsExtensionStubs
{
    #[Before]
    #[After]
    public function resetExtensionStubs(): void
    {
        ExtensionLog::reset();
        AugmentationLog::reset();
        EngineLog::reset();
        EventLog::reset();
        MetaLog::reset();
        RestrictionLog::reset();
        RowCheckLog::reset();
        WriteLog::reset();
        MaskingFields::reset();
        OtherMaskingFields::reset();
        StampRoutes::reset();
        OtherStampRoutes::reset();
        StampsRequests::$stamps = [];
        RoutedStampNamedLimits::$built = 0;
        FirstCacheContext::reset();
        SecondCacheContext::reset();
        FirstGates::reset();
        SecondGates::reset();
        FirstNarrowing::$narrow = null;
        SecondNarrowing::$narrow = null;
        ClausedExtension::$clause = null;
        DeclaredIndexExtension::$declared = [];
        ReadersExtension::$excludes = false;
        ReadersExtension::$groups = null;
        SqlForeignEngineProfiles::$skipsRows = false;
        RegionStageMeta::$named = null;
        RecordingReadObserver::$named = null;
        ChangingReadObserver::$named = null;
        FirstResponses::reset();
        SecondResponses::reset();
    }
}
