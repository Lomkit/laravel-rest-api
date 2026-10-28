<?php

namespace Lomkit\Rest\Tests\Support\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Lomkit\Rest\Tests\Support\Models\NoRelationshipAuthorizedModel;

class NoRelationshipAuthorizationModelFactory extends ModelFactory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<Model|TModel>
     */
    protected $model = NoRelationshipAuthorizedModel::class;
}
