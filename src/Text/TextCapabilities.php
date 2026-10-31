<?php

namespace Lomkit\Rest\Text;

final readonly class TextCapabilities
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
    ) {}

    /** @param list<TextMode> $modes */
    public static function of(TextTarget $target, array $modes, bool $relevance): self
    {
        return new self($target->fields->names(), $modes, $relevance, $target->softDeletes());
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
}
