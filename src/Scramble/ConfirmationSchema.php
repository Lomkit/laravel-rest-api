<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Types\ObjectType;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\Routing\Endpoint;

final readonly class ConfirmationSchema
{
    /** @return array<string, StringType> */
    public static function token(Resource $resource, Endpoint $endpoint): array
    {
        if (! $resource->confirms($endpoint)) {
            return [];
        }

        return ['confirmation' => (new StringType)->setDescription('The token of the 428 answer of this endpoint, sent back with the same body to write')];
    }

    public static function answer(): ObjectType
    {
        return Schemas::required([
            'message' => new StringType,
            'confirmation' => Schemas::closed([
                'token' => (new StringType)->setDescription('A signed token, valid for this user, this endpoint and this body (and for an action, this number of rows) until expires_at'),
                'expires_at' => (new StringType)->format('date-time'),
                'data' => (new ObjectType)->setDescription('The data that the action or the resource gives to its confirmation'),
            ], ['token', 'expires_at', 'data']),
        ]);
    }
}
