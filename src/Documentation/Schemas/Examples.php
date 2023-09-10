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

    /**
     * Set multiple examples.
     *
     * @param  array  $examples
     * @return Examples
     */
    public function withExamples(array $examples): Examples
    {
        $this->examples = array_merge($this->examples, $examples);
        return $this;
    }

    /**
     * Get the array of examples.
     *
     * @return array
     */
    public function examples(): array
    {
        return $this->examples;
    }

    /**
     * Serialize the examples as an array.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return collect($this->examples())->map->jsonSerialize()->toArray();
    }

    /**
     * Generate the examples.
     *
     * @return Examples
     */
    public function generate(): Examples
    {
        return $this;
    }

    /**
     * Generate example details for a specific controller.
     *
     * @param  Controller  $controller
     * @return Examples
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
                        )
                ]
            )
            ->generate();
    }
}