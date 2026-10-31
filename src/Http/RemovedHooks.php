<?php

namespace Lomkit\Rest\Http;

use Lomkit\Rest\Exceptions\InvalidInstructionException;
use Lomkit\Rest\Exceptions\InvalidResourceException;
use Lomkit\Rest\Instructions\Instruction;

final class RemovedHooks
{
    private const array RESOURCE = [
        'scoutFields' => 'Declare the index attributes with ScoutEngine::make()->filterable() and ->sortable() in text().',
        'scoutInstructions' => 'Declare these instructions in instructions() and implement AppliesToEngine on them.',
        'searchScoutQuery' => 'Declare the index perimeter with ScoutEngine::make()->perimeter() in text().',
    ];

    private const array INSTRUCTION = [
        'handleScout' => 'Implement AppliesToEngine and move the method to handleEngine(array $fields, EngineQuery $query).',
    ];

    public static function ensureAbsentFrom(Resource $resource): void
    {
        foreach (self::RESOURCE as $method => $replacement) {
            if (method_exists($resource, $method)) {
                throw InvalidResourceException::removedHook($resource::class, $method, $replacement);
            }
        }
    }

    public static function ensureAbsentFromInstruction(Instruction $instruction): void
    {
        foreach (self::INSTRUCTION as $method => $replacement) {
            if (method_exists($instruction, $method)) {
                throw InvalidInstructionException::removedHook($instruction::class, $method, $replacement);
            }
        }
    }
}
