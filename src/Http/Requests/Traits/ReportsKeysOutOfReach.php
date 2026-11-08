<?php

namespace Lomkit\Rest\Http\Requests\Traits;

use Illuminate\Contracts\Validation\Validator;
use Lomkit\Rest\Validation\ReachLog;

trait ReportsKeysOutOfReach
{
    private ?ReachLog $reachLog = null;

    protected function reachLog(): ReachLog
    {
        return $this->reachLog ??= new ReachLog;
    }

    #[\Override]
    protected function failedValidation(Validator $validator): void
    {
        $endpoint = $this->endpoint();

        if ($this->reachLog !== null && $endpoint !== null) {
            $this->reachLog->report($this->context(), $endpoint);
        }

        parent::failedValidation($validator);
    }
}
