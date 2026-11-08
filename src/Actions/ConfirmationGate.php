<?php

namespace Lomkit\Rest\Actions;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Date;
use Lomkit\Rest\Actions\Rows\ActionRows;
use Lomkit\Rest\Authorization\UserIdentity;
use Lomkit\Rest\Exceptions\ConfirmationRequired;
use Lomkit\Rest\Exceptions\InvalidConfigException;
use Lomkit\Rest\Http\Requests\OperateRequest;

final readonly class ConfirmationGate
{
    private const array BODY = ['search', 'resources', 'fields'];

    public function __construct(
        private OperateRequest $request,
        private Action $action,
        private ActionFields $fields,
    ) {}

    public function pass(ActionRows $rows): void
    {
        if (! $this->action->requiresConfirmation()) {
            return;
        }

        $count = $rows->count();
        $confirmation = $this->action->confirmation($this->fields, $count);

        if ($confirmation === null) {
            return;
        }

        $token = ConfirmationToken::for(self::key(), $this->subject($count));
        $now = Date::now()->toImmutable();

        if ($token->accepts($this->request->payload()->confirmation, $now->getTimestamp())) {
            return;
        }

        $expires = $now->addSeconds($confirmation->seconds);

        throw new ConfirmationRequired($confirmation, $token->issue($expires->getTimestamp()), $expires);
    }

    /** @return list<mixed> */
    private function subject(int $rows): array
    {
        $body = json_encode(self::canonical(Arr::only($this->request->all(), self::BODY)), JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        return [
            UserIdentity::of(Auth::getDefaultDriver(), $this->request->user())->key(),
            $this->action->resource::class,
            $this->action->uriKey(),
            hash('sha256', $body),
            $rows,
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
