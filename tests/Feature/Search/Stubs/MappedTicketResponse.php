<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Response;

class MappedTicketResponse extends Response
{
    /**
     * @param  array<string, mixed>  $responseModel
     * @return array<string, mixed>
     */
    #[\Override]
    protected function map(Model $model, array $responseModel): array
    {
        return [...$responseModel, 'closed' => $model->getAttribute('status') === 'closed'];
    }
}
