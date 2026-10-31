<?php

namespace Lomkit\Rest\Exceptions;

use InvalidArgumentException;

final class UndiscoverablePolicyException extends InvalidArgumentException
{
    public static function for(string $model, string $policy): self
    {
        return new self("Gate finds no policy for the model [{$model}] and would not discover the policy [{$policy}] that this command generates. Register a policy for the model with Gate::policy(), for example one generated with php artisan rest:policy, then run this command again.");
    }
}
