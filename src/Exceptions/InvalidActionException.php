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

    public static function dataList(): self
    {
        return new self('The data of an ActionResponse is a list, but the result gives it as a JSON object: pass an array with string keys, such as [\'skipped\' => [4, 9]].');
    }

    public static function confirmationDataList(): self
    {
        return new self('The data of an ActionConfirmation is a list, but the answer gives it as a JSON object: pass an array with string keys, such as [\'rows\' => 37].');
    }

    public static function confirmationExpiry(int $seconds): self
    {
        return new self("An ActionConfirmation expires after one second at least, but expiresIn() received {$seconds}.");
    }
}
