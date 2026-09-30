<?php

namespace Lomkit\Rest\Filters;

use Lomkit\Rest\Http\Requests\RestRequest;

trait Filterable
{
    /**
     * The calculated filters if already done in this request.
     *
     * @var array
     */
    protected array $calculatedFilters;

    /**
     * The filters that should be linked.
     *
     * A filter declared under the name of a field or of a relation path
     * replaces the way this field is filtered.
     *
     * @param RestRequest $request
     *
     * @return array
     */
    public function filters(RestRequest $request): array
    {
        return [];
    }

    /**
     * Get the resource's filters.
     *
     * @param \Lomkit\Rest\Http\Requests\RestRequest $request
     *
     * @return array
     */
    public function getFilters(\Lomkit\Rest\Http\Requests\RestRequest $request): array
    {
        return $this->calculatedFilters ?? ($this->calculatedFilters = $this->filters($request));
    }

    /**
     * Retrieve a specific filter by its field.
     *
     * @param RestRequest $request The REST request instance.
     * @param mixed       $field   The field of the filter to retrieve.
     *
     * @return Filter|null The filter instance or null if not found.
     */
    public function filter(RestRequest $request, $field)
    {
        $filter = collect($this->getFilters($request))
            ->first(function (Filter $filter) use ($field) {
                return $filter->field === $field;
            });

        if (!is_null($filter)) {
            $filter
                ->resource($this);
        }

        return $filter;
    }
}
