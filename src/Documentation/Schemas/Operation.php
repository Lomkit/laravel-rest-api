<?php

namespace Lomkit\Rest\Documentation\Schemas;

class Operation extends Schema
{
    /**
     * A list of tags for API documentation control.
     * Tags can be used for logical grouping of operations by resources or any other qualifier.
     * @var array
     */
    protected array $tags = [];

    /**
     * A short summary of what the operation does.
     * @var string
     */
    protected string $summary;

    /**
     * A verbose explanation of the operation behavior.
     * CommonMark syntax MAY be used for rich text representation.
     * @var string
     */
    protected string $description;

    /**
     * The list of possible responses as they are returned from executing this operation.
     * @var Responses
     */
    protected Responses $responses;

    public function withTags(array $tags): Operation
    {
        $this->tags = array_merge($this->tags, $tags);
        return $this;
    }

    public function tags(): array
    {
        return $this->tags;
    }

    public function withSummary(string $summary): Operation
    {
        $this->summary = $summary;
        return $this;
    }

    public function summary(): string
    {
        return $this->summary;
    }

    public function withDescription(string $description): Operation
    {
        $this->description = $description;
        return $this;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function withResponses(Responses $responses): Operation
    {
        $this->responses = $responses;
        return $this;
    }

    public function responses(): Responses
    {
        return $this->responses;
    }

    public function jsonSerialize(): mixed
    {
        return [
            'tags' => $this->tags(),
            'summary' => $this->summary(),
            'description' => $this->description(),
            'responses' => $this->responses()->jsonSerialize()
        ];
    }
}