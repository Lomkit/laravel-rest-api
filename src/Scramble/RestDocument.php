<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Contracts\DocumentTransformer;
use Dedoc\Scramble\OpenApiContext;
use Dedoc\Scramble\Support\Generator\OpenApi;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Support\Generator\Reference;

final readonly class RestDocument implements DocumentTransformer
{
    #[\Override]
    public function handle(OpenApi $document, OpenApiContext $context): void
    {
        $dropped = [];

        foreach ($document->paths as $index => $path) {
            $path->operations = array_filter($path->operations, fn (Operation $operation): bool => $operation->getAttribute(RestOperations::OMITTED) !== true);

            if ($path->operations === []) {
                unset($document->paths[$index]);
            }

            foreach ($path->operations as $operation) {
                array_push($dropped, ...self::dropped($operation));
            }
        }

        $document->paths = array_values($document->paths);

        foreach (array_diff(array_unique($dropped), self::referenced($document)) as $name) {
            $document->components->removeResponse($name);
        }

        ksort($document->components->responses);
    }

    /** @return list<string> */
    private static function dropped(Operation $operation): array
    {
        $names = $operation->getAttribute(RestOperations::DROPPED_RESPONSES, []);

        return is_array($names) ? array_values(array_filter($names, is_string(...))) : [];
    }

    /** @return list<string> */
    private static function referenced(OpenApi $document): array
    {
        $names = [];

        foreach ($document->paths as $path) {
            foreach ($path->operations as $operation) {
                foreach ($operation->responses ?? [] as $response) {
                    if ($response instanceof Reference && $response->referenceType === 'responses' && $response->fullName !== null) {
                        $names[] = $response->fullName;
                    }
                }
            }
        }

        return $names;
    }
}
