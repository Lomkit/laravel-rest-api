<?php

namespace Lomkit\Rest\Documentation\Schemas;

use Lomkit\Rest\Http\Controllers\Controller;

class Examples extends Schema
{
    /**
     * Examples
     * @var array
     */
    protected array $examples = [];

    public function withExamples(array $examples): Examples
    {
        $this->examples = array_merge($this->examples, $examples);
        return $this;
    }

    public function examples(): array
    {
        return $this->examples;
    }

    public function jsonSerialize(): mixed
    {
        return collect($this->examples())->map->jsonSerialize()->toArray();
    }

    public function generate(): Examples
    {
        return $this;
    }

    public function generateDetail(Controller $controller): Examples
    {
        return $this
            ->withExamples(
                [
                    'application/json' => (new Example)
                        ->withExample(
                            json_encode(
                                ['test1234' => 'OAZEOOEZAOEOAZ']
                            )
                        )
                ]
            )
            ->generate();
    }
}