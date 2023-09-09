<?php

namespace Lomkit\Rest\Concerns;

trait Metable
{
    /**
     * The meta array.
     *
     * @var array<string, mixed>
     */
    public $meta = [];

    /**
     * Get the meta data.
     *
     * @return array
     */
    public function meta()
    {
        return $this->meta;
    }

    /**
     * Set additional meta information for the element.
     *
     * @param array $meta
     *
     * @return $this
     */
    public function withMeta(array $meta)
    {
        return tap($this, function () use ($meta) {
            $this->meta = array_merge($this->meta, $meta);
        });
    }
}
