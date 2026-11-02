<?php

namespace Lomkit\Rest\Validation\Search;

use Illuminate\Validation\Rule;
use Lomkit\Rest\Text\TextCapabilities;
use Lomkit\Rest\Text\TextMode;
use Lomkit\Rest\Text\TextQuery;
use Lomkit\Rest\Validation\Cursor;
use Lomkit\Rest\Validation\ValidationContext;

final readonly class TextParser
{
    private const array KEYS = ['value' => true, 'mode' => true, 'trashed' => true];

    public function __construct(private TextCapabilities $text) {}

    public function parse(Cursor $text, ValidationContext $context): ?TextQuery
    {
        $rules = $context->rules;
        $mode = $text->at('mode');

        $context->reader->known($text, self::KEYS);
        $rules->add($text->at('value')->path, ['nullable', 'string', 'max:'.$this->text->maxLength]);

        if ($mode->present) {
            $rules->add($mode->path, ['string', Rule::enum(TextMode::class)->only($this->text->modes)]);
            $rules->needsValue($mode->path, $mode->value);
        }

        $rules->add($text->at('trashed')->path, ['prohibited']);

        if ($mode->present && ! $this->supports($mode->value)) {
            $rules->partial();
        }

        return TextQuery::fromSearch($text->value);
    }

    private function supports(mixed $mode): bool
    {
        $parsed = is_string($mode) ? TextMode::tryFrom($mode) : null;

        return $parsed !== null && $this->text->supports($parsed);
    }
}
