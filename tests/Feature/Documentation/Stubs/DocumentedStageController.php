<?php

namespace Lomkit\Rest\Tests\Feature\Documentation\Stubs;

use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\MutateResponse;
use Lomkit\Rest\Http\Requests\MutateRequest;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Http\Response;
use Lomkit\Rest\Tests\Fixtures\Festival\StageResource;

class DocumentedStageController extends Controller
{
    public static string $resource = StageResource::class;

    /**
     * Find the stages of the festival.
     */
    #[\Override]
    public function search(SearchRequest $request): Response
    {
        return parent::search($request);
    }

    /**
     * Write the stages of the festival.
     */
    #[\Override]
    public function mutate(MutateRequest $request): MutateResponse
    {
        return parent::mutate($request);
    }
}
