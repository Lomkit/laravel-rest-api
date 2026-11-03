<?php

namespace Lomkit\Rest\Tests\Feature\Search\Stubs;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Lomkit\Rest\Http\Response;

class SignedAuthorResponse extends Response
{
    /**
     * @param  array<string, mixed>  $responseModel
     * @return array<string, mixed>
     */
    #[\Override]
    protected function map(Model $model, array $responseModel): array
    {
        return [...$responseModel, 'token_hint' => Str::after((string) $model->getAttribute('api_token'), 'token-')];
    }
}
