<?php

namespace Lomkit\Rest\Authorization;

use Illuminate\Auth\Access\Response;

final readonly class Decision
{
    public function __construct(
        public bool $allowed,
        public ?string $message = null,
        public int|string|null $code = null,
        public ?int $status = null,
    ) {}

    public static function allow(): self
    {
        return new self(true);
    }

    public static function fromResponse(Response $response): self
    {
        return new self($response->allowed(), $response->message(), self::scalarCode($response->code()), $response->status());
    }

    /** @param array<array-key, mixed> $data */
    public static function fromArray(array $data): self
    {
        $message = $data['message'] ?? null;
        $status = $data['status'] ?? null;

        return new self(
            ($data['allowed'] ?? false) === true,
            is_string($message) ? $message : null,
            self::scalarCode($data['code'] ?? null),
            is_int($status) ? $status : null,
        );
    }

    public function toResponse(): Response
    {
        return (new Response($this->allowed, $this->message, $this->code))->withStatus($this->status);
    }

    /** @return array{allowed: bool, message: ?string, code: int|string|null, status: ?int} */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed,
            'message' => $this->message,
            'code' => $this->code,
            'status' => $this->status,
        ];
    }

    private static function scalarCode(mixed $code): int|string|null
    {
        return is_int($code) || is_string($code) ? $code : null;
    }
}
