<?php

namespace Lomkit\Rest\Console\Generation;

use PhpToken;

final readonly class ClassFile
{
    private const array DECLARATIONS = [T_CLASS, T_INTERFACE, T_TRAIT, T_ENUM];

    private const array MODIFIERS = [T_ABSTRACT, T_FINAL, T_READONLY];

    /** @param  list<PhpToken>  $tokens */
    private function __construct(private array $tokens) {}

    public static function read(string $path): self
    {
        $tokens = PhpToken::tokenize((string) file_get_contents($path));

        return new self(array_values(array_filter($tokens, fn (PhpToken $token): bool => ! $token->isIgnorable())));
    }

    public function declaresOnlyTheChildClass(string $class): bool
    {
        $declarations = array_keys(array_filter(
            $this->tokens,
            fn (PhpToken $token, int $index): bool => $token->is(self::DECLARATIONS) && ! $this->isAnonymousOrConstant($index),
            ARRAY_FILTER_USE_BOTH,
        ));

        if (count($declarations) !== 1) {
            return false;
        }

        $index = $declarations[0];

        return $this->tokens[$index]->is(T_CLASS)
            && ! $this->isAbstract($index)
            && $this->is($index + 2, T_EXTENDS)
            && $this->is($index + 1, T_STRING)
            && $this->namespace().$this->tokens[$index + 1]->text === $class;
    }

    private function isAnonymousOrConstant(int $index): bool
    {
        return $this->is($this->startOfModifiers($index) - 1, [T_NEW, T_DOUBLE_COLON]);
    }

    private function isAbstract(int $index): bool
    {
        for ($modifier = $this->startOfModifiers($index); $modifier < $index; $modifier++) {
            if ($this->is($modifier, T_ABSTRACT)) {
                return true;
            }
        }

        return false;
    }

    private function startOfModifiers(int $index): int
    {
        while ($this->is($index - 1, self::MODIFIERS)) {
            $index--;
        }

        return $index;
    }

    private function namespace(): string
    {
        foreach ($this->tokens as $index => $token) {
            if ($token->is(T_NAMESPACE) && $this->is($index + 1, [T_STRING, T_NAME_QUALIFIED])) {
                return $this->tokens[$index + 1]->text.'\\';
            }
        }

        return '';
    }

    /** @param  int|list<int>  $kind */
    private function is(int $index, int|array $kind): bool
    {
        return isset($this->tokens[$index]) && $this->tokens[$index]->is($kind);
    }
}
