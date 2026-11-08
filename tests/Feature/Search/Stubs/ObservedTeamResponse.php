<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Http\Response;

class ObservedTeamResponse extends Response
{
    /** @var array<int, int> */
    public static array $mapped = [];

    /**
     * @param  array<string, mixed>  $responseModel
     * @return array<string, mixed>
     */
    #[\Override]
    protected function map(Model $model, array $responseModel): array
    {
        self::$mapped[(int) $model->getKey()] = ObservedTeamResource::ticketCount($model);

        return $responseModel;
    }
}
