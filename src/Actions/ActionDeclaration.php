<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Contracts\Queue\ShouldQueue;
use Lomkit\Rest\Exceptions\InvalidActionException;
use Lomkit\Rest\Support\Overrides;

final readonly class ActionDeclaration
{
    public function __construct(private Action $action) {}

    public function check(): void
    {
        $this->checkRowChecks();
        $this->checkTransaction();
    }

    private function checkRowChecks(): void
    {
        $ability = $this->action->authorizeEach();

        if ($ability === null && $this->action->skipUnauthorized) {
            throw InvalidActionException::skipWithoutAbility($this->action::class);
        }

        if ($ability !== null && $this->action->target === ActionTarget::None) {
            throw InvalidActionException::abilityWithoutRows($this->action::class, $ability);
        }
    }

    private function checkTransaction(): void
    {
        $transaction = $this->action->transaction;

        if ($transaction === ActionTransaction::Whole && $this->action instanceof ShouldQueue) {
            throw InvalidActionException::wholeTransactionQueued($this->action::class);
        }

        if ($transaction !== ActionTransaction::Row) {
            $this->checkWithoutRowHooks($transaction);

            return;
        }

        if ($this->action->target === ActionTarget::None) {
            throw InvalidActionException::rowTransactionWithoutRows($this->action::class);
        }

        if (! $this->declares('handleRow')) {
            throw InvalidActionException::rowTransactionWithoutHandleRow($this->action::class);
        }

        if ($this->declares('handle')) {
            throw InvalidActionException::rowTransactionWithHandle($this->action::class);
        }
    }

    private function checkWithoutRowHooks(ActionTransaction $transaction): void
    {
        foreach (['handleRow', 'beforeRow'] as $hook) {
            if ($this->declares($hook)) {
                throw InvalidActionException::rowHookWithoutRowTransaction($this->action::class, $hook, $transaction->name);
            }
        }
    }

    private function declares(string $method): bool
    {
        return Overrides::method($this->action, $method, Action::class);
    }
}
