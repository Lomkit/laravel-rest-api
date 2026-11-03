<?php

namespace Lomkit\Rest\Tests\Feature\Mutate\Stubs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Response;

class HeadedBookResponse extends Response
{
    /**
     * @param  array<string, mixed>  $responseModel
     * @return array<string, mixed>
     */
    #[\Override]
    protected function map(Model $model, array $responseModel): array
    {
        return [...$responseModel, 'heading' => Str::upper((string) $model->getAttribute('title'))];
    }
}
