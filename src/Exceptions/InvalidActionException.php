<?php

namespace Lomkit\Rest\Exceptions;

use LogicException;

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

    public static function dataList(): self
    {
        return new self('The data of an ActionResponse is a list, but the result gives it as a JSON object: pass an array with string keys, such as [\'skipped\' => [4, 9]].');
    }
}
