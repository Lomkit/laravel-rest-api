<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Auth\AuthManager;
use Illuminate\Contracts\Auth\Authenticatable;
use Lomkit\Rest\Authorization\UserReference;
use Lomkit\Rest\Fields\CastValues;
use Lomkit\Rest\Fields\FieldValues;
use Lomkit\Rest\Fields\InputValues;

final readonly class ActionFields extends FieldValues
{
    private function __construct(
        CastValues $values,
        private ActionTarget $target,
        private string $guard,
        private ?Authenticatable $user,
    ) {
        parent::__construct($values);
    }

    public static function of(Action $action, InputValues $inputs, string $guard, ?Authenticatable $user): self
    {
        return new self(CastValues::of($inputs, $action::class.'::fields()'), $action->target, $guard, $user);
    }

    public function user(): ?Authenticatable
    {
        return $this->user;
    }

    public function target(): ActionTarget
    {
        return $this->target;
    }

    /** @return array{values: CastValues, target: ActionTarget, user: UserReference} */
    public function __serialize(): array
    {
        return ['values' => $this->values, 'target' => $this->target, 'user' => UserReference::of($this->guard, $this->user)];
    }

    /** @param array{values: CastValues, target: ActionTarget, user: UserReference} $data */
    public function __unserialize(array $data): void
    {
        $this->__construct($data['values'], $data['target'], $data['user']->guard, $data['user']->resolve(app(AuthManager::class)));
    }
}
