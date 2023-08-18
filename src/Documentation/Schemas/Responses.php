<?php

namespace Lomkit\Rest\Documentation\Schemas;

class Responses extends Schema
{
    /**
     * The documentation of responses other than the ones declared for specific HTTP response codes. Use this field to cover undeclared responses.
     * @var Response
     */
    protected Response $default;

    /**
     * Other responses
     * @var object
     */
    protected array $others;

    public function withDefault(Response $default): Responses
    {
        $this->default = $default;
        return $this;
    }

    public function default(): Response
    {
        return $this->default;
    }

    public function withOthers(array $others): Responses
    {
        $this->others = array_merge($this->others, $others);
        return $this;
    }

    public function others(): array
    {
        return $this->others;
    }

    public function jsonSerialize(): mixed
    {
        return array_merge(
            ['default' => $this->default()->jsonSerialize()],
            $this->others()
        );
    }
}