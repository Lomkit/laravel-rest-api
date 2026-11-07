<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Database\Eloquent\Model;
use JsonSerializable;
use Lomkit\Rest\Exceptions\InvalidActionException;

final readonly class ActionResponse implements JsonSerializable
{
    /**
     * @param  array<string, mixed>  $values
     * @param  array<mixed>|null  $data
     */
    private function __construct(
        public string $type,
        public array $values,
        public ?array $data = null,
    ) {
        if ($data !== null && $data !== [] && array_is_list($data)) {
            throw InvalidActionException::dataList();
        }
    }

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

    /** @param array<string, mixed> $data */
    public static function data(array $data): self
    {
        return new self('data', [], $data);
    }

    /** @param array<string, mixed> $data */
    public function withData(array $data): self
    {
        return new self($this->type, $this->values, $data);
    }

    /** @return array<string, mixed> */
    #[\Override]
    public function jsonSerialize(): array
    {
        if ($this->data === null) {
            return ['type' => $this->type, ...$this->values];
        }

        return ['type' => $this->type, ...$this->values, 'data' => (object) $this->data];
    }
}
