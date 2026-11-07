<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Database\Eloquent\Model;
use JsonSerializable;

final readonly class ActionResponse implements JsonSerializable
{
    /** @param array<string, mixed> $values */
    private function __construct(
        public string $type,
        public array $values,
    ) {}

    public static function message(string $text): self
    {
        return new self('message', ['message' => $text]);
    }

    public static function danger(string $text): self
    {
        return new self('danger', ['message' => $text]);
    }

    public static function download(string $url, string $name): self
    {
        return new self('download', ['url' => $url, 'name' => $name]);
    }

    public static function redirect(string $url): self
    {
        return new self('redirect', ['url' => $url]);
    }

    /** @param iterable<Model> $models */
    public static function created(iterable $models): self
    {
        $keys = [];

        foreach ($models as $model) {
            $keys[] = $model->getKey();
        }

        return new self('created', ['keys' => $keys]);
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function jsonSerialize(): array
    {
        return ['type' => $this->type, ...$this->values];
    }
}
