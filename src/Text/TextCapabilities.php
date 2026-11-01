<?php

namespace Lomkit\Rest\Text;

use JsonSerializable;
use Lomkit\Rest\Text\Engine\EngineGrammar;
use Lomkit\Rest\Text\Engine\IndexAttribute;

final readonly class TextCapabilities implements JsonSerializable
{
    public const int MAX_LENGTH = 255;

    /**
     * @param  list<string>  $fields
     * @param  list<TextMode>  $modes
     */
    public function __construct(
        public array $fields,
        public array $modes,
        public bool $relevance,
        public bool $trashed,
        public int $maxLength = self::MAX_LENGTH,
        public ?EngineGrammar $engine = null,
    ) {}

    /** @param list<TextMode> $modes */
    public static function of(TextTarget $target, array $modes, bool $relevance): self
    {
        return new self($target->names(), $modes, $relevance, $target->softDeletes());
    }

    public function supports(TextMode $mode): bool
    {
        return in_array($mode, $this->modes, true);
    }

    /** @return list<string> */
    public function modeNames(): array
    {
        return array_map(fn (TextMode $mode): string => $mode->value, $this->modes);
    }

    /** @return array{fields: list<string>, modes: list<string>, relevance: bool, trashed: bool, max_length: int, engine: array{filters: list<IndexAttribute>, sorts: list<string>, max_sorts: int|null, max_page_size: int|null, window: int|null}|null} */
    #[\Override]
    public function jsonSerialize(): array
    {
        return [
            'fields' => $this->fields,
            'modes' => $this->modeNames(),
            'relevance' => $this->relevance,
            'trashed' => $this->trashed,
            'max_length' => $this->maxLength,
            'engine' => $this->engine?->jsonSerialize(),
        ];
    }
}
