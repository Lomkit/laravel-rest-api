<?php

namespace Lomkit\Rest\Documentation\Schemas;

use Lomkit\Rest\Http\Controllers\Controller;

class Examples extends Schema
{
    /**
     * Examples.
     */
    protected array $examples = [];

    /**
     * Set multiple examples.
     */
    public function withExamples(array $examples): Examples
    {
        $this->examples = array_merge($this->examples, $examples);

        return $this;
    }

    /**
     * Get the array of examples.
     */
    public function examples(): array
    {
        return $this->examples;
    }

    /**
     * Serialize the examples as an array.
     */
    public function jsonSerialize(): mixed
    {
        return collect($this->examples())->map->jsonSerialize()->toArray();
    }

    /**
     * Generate the examples.
     */
    public function generate(): Examples
    {
        return $this;
    }

    /**
     * Generate example details for a specific controller.
     */
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
                        ),
                ]
            )
            ->generate();
    }
}
