<?php

namespace Lomkit\Rest\Text;

final readonly class TextQuery
{
    public function __construct(
        public string $value,
        public TextMode $mode = TextMode::Words,
    ) {}

    public static function fromSearch(mixed $text): ?self
    {
        $value = is_array($text) ? ($text['value'] ?? null) : null;

        if (! is_array($text) || ! is_string($value) || trim($value) === '') {
            return null;
        }

        $mode = is_string($text['mode'] ?? null) ? TextMode::tryFrom($text['mode']) : null;

        return new self($value, $mode ?? TextMode::Words);
    }

    /** @return list<string> */
    public function terms(TermPattern $pattern = TermPattern::Alphanumeric): array
    {
        return $pattern->terms($this->value);
    }

    public function hasTerms(): bool
    {
        return $this->terms() !== [];
    }

    /** @return list<string> */
    public function words(): array
    {
        $words = preg_split('/\s+/u', $this->value, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter($words === false ? [] : $words, fn (string $word): bool => TermPattern::Alphanumeric->terms($word) !== []));
    }

    public function phrase(): string
    {
        return implode(' ', preg_split('/\s+/u', trim($this->value), -1, PREG_SPLIT_NO_EMPTY) ?: []);
    }
}
