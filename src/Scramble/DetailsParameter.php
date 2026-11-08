<?php

namespace Lomkit\Rest\Scramble;

use Dedoc\Scramble\Support\Generator\Parameter;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Types\StringType;
use Lomkit\Rest\Http\Details\DetailsSelection;

final class DetailsParameter
{
    public static function make(): Parameter
    {
        $schema = new Schema;
        $schema->type = new StringType;

        $parameter = Parameter::make('with', 'query');
        $parameter->setSchema($schema);
        $parameter->description = 'The sections and parts to add to the fields, limits and meta of the response, separated by commas. '
            .'A part adds its section. Accepted values: '.implode(', ', DetailsSelection::values()).'.';
        $parameter->example = 'fields.capabilities,fields.filter,scopes.fields,actions.authorized';

        return $parameter;
    }
}
