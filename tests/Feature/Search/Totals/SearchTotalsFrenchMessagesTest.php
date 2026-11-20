<?php

namespace Lomkit\Rest\Tests\Feature\Search\Totals;

use Illuminate\Support\Facades\Route;
use Lomkit\Rest\Facades\Rest;
use Lomkit\Rest\Tests\Feature\Search\Totals\Stubs\CappedShowController;
use Lomkit\Rest\Tests\Feature\Search\Totals\Stubs\PagedShowController;
use PHPUnit\Framework\Attributes\Test;

class SearchTotalsFrenchMessagesTest extends SearchTotalsTestCase
{
    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->setLocale('fr');
        Route::prefix('api')->group(static function (): void {
            Rest::resource('capped-shows', CappedShowController::class)->register();
            Rest::resource('paged-shows', PagedShowController::class)->register();
        });
    }

    #[Test]
    public function it_answers_the_totals_refusals_in_french(): void
    {
        $empty = $this->search(['totals' => []]);
        $capped = $this->search(['totals' => ['groups' => [['field' => 'status']]]], 'capped-shows');
        $paged = $this->search(['totals' => ['groups' => [['field' => 'status']]]], 'paged-shows');

        $empty->assertUnprocessable()->assertJsonPath('errors', ['search.totals' => ['Le champ search.totals doit demander des mesures ou des groupes.']]);
        $capped->assertUnprocessable()->assertJsonPath('errors', ['search.totals' => ['Le champ search.totals est interdit : cette ressource plafonne le décompte de ses lignes.']]);
        $paged->assertUnprocessable()->assertJsonPath('errors', ['search.totals' => ['Le champ search.totals est interdit : cette ressource compte ses pages elle-même.']]);
    }
}
