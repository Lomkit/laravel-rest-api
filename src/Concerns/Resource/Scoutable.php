<?php

namespace Lomkit\Rest\Concerns\Resource;

use Lomkit\Rest\Http\Requests\RestRequest;

trait Scoutable
{
    /**
     * The calculated scout fields if already done in this request.
     *
     * @var array
     */
    protected array $calculatedScoutFields;

    /**
     * The scout fields that could be provided.
     *
     * @param RestRequest $request
     *
     * @return array
     */
    public function scoutFields(RestRequest $request): array
    {
        return [];
    }

    /**
     * Get the resource's scout fields.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest $request
     *
     * @return array
     */
    public function getScoutFields(\Lomkit\Rest\Http\Requests\RestRequest $request): array
    {
        return $this->calculatedScoutFields ?? ($this->calculatedScoutFields = $this->scoutFields($request));
    }
}
