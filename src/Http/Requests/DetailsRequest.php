<?php

namespace Lomkit\Rest\Http\Requests;

use LogicException;
use Lomkit\Rest\Http\Details\DetailsSelection;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Rules\DetailsParts;

class DetailsRequest extends RestRequest
{
    #[\Override]
    public function endpoint(): Endpoint
    {
        return Endpoint::Details;
    }

    /** @return array<string, list<mixed>> */
    #[\Override]
    public function rules(): array
    {
        return [
            'with' => ['nullable', 'string', new DetailsParts],
        ];
    }

    public function selection(): DetailsSelection
    {
        $with = $this->input('with');

        if (! $this->hasPassedValidation() || ($with !== null && ! is_string($with))) {
            throw new LogicException('The details selection was read before the request passed validation.');
        }

        return DetailsSelection::parse($with);
    }
}
