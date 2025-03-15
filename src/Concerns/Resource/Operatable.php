<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Http\Requests\RestRequest;

trait Operatable
{
    /**
     * The calculated operators if already done in this request.
     *
     * @var array
     */
    protected array $calculatedOperators;

    /**
     * The operators that could be provided.
     *
     * @param RestRequest $request
     *
     * @return array
     */
    public function operators(RestRequest $request): array
    {
        return [
            '=',
            '!=',
            '>',
            '>=',
            '<',
            '<=',
            'like',
            'not like',
            'in',
            'not in'
        ];
    }

    /**
     * Get the resource's operators.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest $request
     *
     * @return array
     */
    public function getOperators(\Lomkit\Rest\Http\Requests\RestRequest $request): array
    {
        return $this->calculatedOperators ?? ($this->calculatedOperators = $this->operators($request));
    }
}
