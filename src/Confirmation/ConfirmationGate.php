<?php

namespace Lomkit\Rest\Confirmation;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Lomkit\Rest\Authorization\UserIdentity;
use Lomkit\Rest\Exceptions\ConfirmationRequired;
use Lomkit\Rest\Exceptions\InvalidConfigException;
use Lomkit\Rest\Http\Requests\RestRequest;

final class ConfirmationGate
{
    private bool $passed = false;

    /** @param Closure(): list<mixed> $subject */
    private function __construct(
        private readonly Closure $subject,
        private readonly ?string $offered,
    ) {}

    /**
     * @param  list<int|string>  $scope
     * @param  list<string>  $body
     */
    public static function of(RestRequest $request, array $scope, array $body): self
    {
        $offered = $request->input('confirmation');

        return new self(fn (): array => self::subject($request, $scope, $body), is_string($offered) ? $offered : null);
    }

    /** @param Closure(): ?Confirmation $ask */
    public function pass(Closure $ask): void
    {
        if ($this->passed) {
            return;
        }

        $confirmation = $ask();

        if ($confirmation !== null) {
            $this->check($confirmation);
        }

        $this->passed = true;
    }

    private function check(Confirmation $confirmation): void
    {
        $token = ConfirmationToken::for(self::key(), ($this->subject)());
        $now = Date::now()->toImmutable();

        if ($token->accepts($this->offered, $now->getTimestamp())) {
            return;
        }

        $expires = $now->addSeconds($confirmation->seconds);

        throw new ConfirmationRequired($confirmation, $token->issue($expires->getTimestamp()), $expires);
    }

    /**
     * @param  list<int|string>  $scope
     * @param  list<string>  $body
     * @return list<mixed>
     */
    private static function subject(RestRequest $request, array $scope, array $body): array
    {
        $signed = json_encode(self::canonical(Arr::only($request->all(), $body)), JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            UserIdentity::of(Auth::getDefaultDriver(), $request->user())->key(),
            $request->resource::class,
            ...$scope,
            hash('sha256', $signed),
        ];
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::canonical(...), $value);
    }

    private static function key(): string
    {
        $key = Config::get('app.key');

        if (! is_string($key) || $key === '') {
            throw InvalidConfigException::missingAppKey();
        }

        return $key;
    }
}
