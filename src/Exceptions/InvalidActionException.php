<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;
use Lomkit\Rest\Actions\Action;

final class InvalidActionException extends LogicException
{
    public static function skipWithoutAbility(string $action): self
    {
        return new self("{$action} calls skipUnauthorized(), but its authorizeEach() returns null: no row is checked, so no row can be skipped.");
    }

    public static function abilityWithoutRows(string $action, string $ability): self
    {
        return new self("{$action} checks [{$ability}] on each row through authorizeEach(), but its target is None: it runs without rows.");
    }

    public static function wholeTransactionQueued(string $action): self
    {
        return new self("{$action} runs its chunks in one transaction (ActionTransaction::Whole), but it implements ShouldQueue: its chunks run in the worker, outside the request.");
    }

    public static function rowTransactionWithoutRows(string $action): self
    {
        return new self("{$action} runs each row in its own transaction (ActionTransaction::Row), but its target is None: it runs without rows.");
    }

    public static function rowTransactionWithoutHandleRow(string $action): self
    {
        return new self("{$action} runs each row in its own transaction (ActionTransaction::Row), but it does not declare handleRow(): with Row, each row reaches handleRow(ActionFields \$fields, Model \$row) instead of handle().");
    }

    public static function rowTransactionWithHandle(string $action): self
    {
        return new self("{$action} runs each row in its own transaction (ActionTransaction::Row), but it declares handle(): with Row, each row reaches handleRow() and handle() never runs.");
    }

    public static function rowHookWithoutRowTransaction(string $action, string $hook, string $transaction): self
    {
        return new self("{$action} declares {$hook}(), but it runs with ActionTransaction::{$transaction}: only ActionTransaction::Row (->transactionPerRow()) calls it.");
    }

    public static function notAnAction(string $class): self
    {
        return new self("[{$class}] is listed in actions(), but it does not extend ".Action::class.'.');
    }

    public static function baseListed(): self
    {
        return new self('actions() lists '.Action::class.' itself. List a class that extends it.');
    }

    public static function notInstantiable(string $class): self
    {
        return new self("{$class} is listed in actions(), but it is abstract or cannot be instantiated. List a concrete class.");
    }

    public static function foreignBinding(string $class, string $bound): self
    {
        return new self("The container builds [{$bound}] for {$class}, listed in actions(): bind {$class} to itself or to a class that extends it.");
    }

    public static function reboundRoute(string $class, string $bound): self
    {
        return new self("The container builds {$bound} for {$class}, listed in actions() by its class, and {$bound} has another route key or other middleware. The binding changes the route: list {$bound} instead, or bind a class with the same route.");
    }

    public static function staleRoute(string $class): self
    {
        return new self("{$class} has another route key or other middleware than its route was registered with. Run php artisan route:cache again, or stop changing its route in its constructor.");
    }

    public static function dynamicKey(string $action, string $method): self
    {
        return new self("{$action} is listed by its class, but it overrides {$method}(): its route key must be read without building it. Set \$uriKey for the route key and \$name for the label, or list the action as an instance.");
    }

    public static function changedRoute(string $action): self
    {
        return new self("{$action} is listed by its class, but its built instance has another route key or other middleware than its class declares. Set \$uriKey, \$name, \$middleware and \$withoutMiddleware as property defaults, or list the action as an instance.");
    }

    public static function dataList(): self
    {
        return new self('The data of an ActionResponse is a list, but the result gives it as a JSON object: pass an array with string keys, such as [\'skipped\' => [4, 9]].');
    }
}
