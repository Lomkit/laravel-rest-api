<?php

namespace Lomkit\Rest\Fields\Concerns;

trait ConfiguresMessages
{
    private ?string $label = null;

    /** @var array<string, string> */
    private array $messages = [];

    public function label(string $label): static
    {
        $this->label = trim($label) === '' ? null : $label;

        return $this;
    }

    public function getLabel(): ?string
    {
        return $this->label;
    }

    /** @param array<string, string> $messages */
    public function messages(array $messages): static
    {
        $this->messages = [...$this->messages, ...$messages];

        return $this;
    }

    /** @return array<string, string> */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
