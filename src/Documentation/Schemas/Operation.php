<?php

namespace Lomkit\Rest\Documentation\Schemas;

use Illuminate\Support\Str;
use Lomkit\Rest\Http\Controllers\Controller;

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
     * The request body applicable for this operation.
     * @var RequestBody
     */
    protected RequestBody $requestBody;

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

    /**
     * Set the list of tags for the operation.
     *
     * @param  array  $tags
     * @return Operation
     */
    public function tags(): array
    {
        return $this->tags;
    }

    /**
     * Set the short summary of the operation.
     *
     * @param  string  $summary
     * @return Operation
     */
    public function withSummary(string $summary): Operation
    {
        $this->summary = $summary;
        return $this;
    }

    /**
     * Get the short summary of the operation.
     *
     * @return string
     */
    public function summary(): string
    {
        return $this->summary;
    }

    /**
     * Set the verbose explanation of the operation behavior.
     *
     * @param  string  $description
     * @return Operation
     */
    public function withDescription(string $description): Operation
    {
        $this->description = $description;
        return $this;
    }

    /**
     * Get the verbose explanation of the operation behavior.
     *
     * @return string
     */
    public function description(): string
    {
        return $this->description;
    }

    /**
     * Set the possible responses for the operation.
     *
     * @param  Responses  $responses
     * @return Operation
     */
    public function withResponses(Responses $responses): Operation
    {
        $this->responses = $responses;
        return $this;
    }

    /**
     * Get the possible responses for the operation.
     *
     * @return Responses
     */
    public function responses(): Responses
    {
        return $this->responses;
    }

    /**
     * Set the request body applicable for the operation.
     *
     * @param  RequestBody  $requestBody
     * @return Operation
     */
    public function withRequestBody(RequestBody $requestBody): Operation
    {
        $this->requestBody = $requestBody;
        return $this;
    }

    /**
     * Get the request body applicable for the operation.
     *
     * @return RequestBody
     */
    public function requestBody(): RequestBody
    {
        return $this->requestBody;
    }

    /**
     * Serialize the Operation object to JSON format.
     *
     * @return mixed
     */
    public function jsonSerialize(): mixed
    {
        return array_merge(
            isset($this->tags) ? ['tags' => $this->tags()] : [],
            isset($this->summary) ? ['summary' => $this->summary()] : [],
            isset($this->responses) ? ['responses' => $this->responses()->jsonSerialize()] : [],
            isset($this->description) ? ['description' => $this->description()] : [],
            isset($this->requestBody) ? ['requestBody' => $this->requestBody()->jsonSerialize()] : []
        );
    }

    /**
     * Generate and return a new instance of this Operation schema.
     *
     * @return Schema
     */
    public function generate(): Schema
    {
        return $this;
    }

    /**
     * Generate an Operation schema for the "Detail" operation.
     *
     * @param  Controller  $controller
     * @return Operation
     */
    public function generateDetail(Controller $controller): Operation
    {
        return $controller->generateDocumentationDetailOperation(
            $this
                ->withSummary('Get the resource detail')
                ->withDescription('Get every detail about the resource according to the current user connected')
                ->withResponses(
                    (new Responses)->generateDetail($controller)
                )
                ->withTags([
                    Str::plural((new \ReflectionClass($controller::newResource()::newModel()))->getShortName())
                ])
                ->generate()
        );
    }

    /**
     * Generate an Operation schema for the "Search" operation.
     *
     * @param  Controller  $controller
     * @return Operation
     */
    public function generateSearch(Controller $controller): Operation
    {
        return $controller->generateDocumentationSearchOperation(
            $this
                ->withSummary('Perform a search request')
                ->withDescription('Crunch the Api\'s data with multiple attributes')
                ->withResponses(
                    (new Responses)->generateSearch($controller)
                )
                ->withTags([
                    Str::plural((new \ReflectionClass($controller::newResource()::newModel()))->getShortName())
                ])
                ->withRequestBody(
                    (new RequestBody)->generateSearch($controller)
                )
                ->generate()
        );
    }

    /**
     * Generate an Operation schema for the "Mutate" operation.
     *
     * @param  Controller  $controller
     * @return Operation
     */
    public function generateMutate(Controller $controller): Operation
    {
        return $controller->generateDocumentationMutateOperation(
            $this
                ->withSummary('Perform a mutate request')
                ->withDescription('Create / Modify the database data with multiple options')
                ->withResponses(
                    (new Responses)->generateMutate($controller)
                )
                ->withTags([
                    Str::plural((new \ReflectionClass($controller::newResource()::newModel()))->getShortName())
                ])
                ->withRequestBody(
                    (new RequestBody)->generateMutate($controller)
                )
                ->generate()
        );
    }

    /**
     * Generate an Operation schema for the "Actions" operation.
     *
     * @param  Controller  $controller
     * @return Operation
     */
    public function generateActions(Controller $controller): Operation
    {
        return $controller->generateDocumentationActionsOperation(
            $this
                ->withSummary('Perform an action request')
                ->withDescription('Launch actions')
                ->withResponses(
                    (new Responses)->generateActions($controller)
                )
                ->withTags([
                    Str::plural((new \ReflectionClass($controller::newResource()::newModel()))->getShortName())
                ])
                ->withRequestBody(
                    (new RequestBody)->generateActions($controller)
                )
                ->generate()
        );
    }

    /**
     * Generate an Operation schema for the "Destroy" operation.
     *
     * @param  Controller  $controller
     * @return Operation
     */
    public function generateDestroy(Controller $controller): Operation
    {
        return $controller->generateDocumentationDestroyOperation(
            $this
                ->withSummary('Perform a destroy request')
                ->withDescription('Delete database records using primary key')
                ->withResponses(
                    (new Responses)->generateDestroy($controller)
                )
                ->withTags([
                    Str::plural((new \ReflectionClass($controller::newResource()::newModel()))->getShortName())
                ])
                ->withRequestBody(
                    (new RequestBody)->generateDestroy($controller)
                )
                ->generate()
        );
    }

    /**
     * Generate an Operation schema for the "Restore" operation.
     *
     * @param  Controller  $controller
     * @return Operation
     */
    public function generateRestore(Controller $controller): Operation
    {
        return $controller->generateDocumentationRestoreOperation(
            $this
                ->withSummary('Perform a restore request')
                ->withDescription('Restore a soft deleted record')
                ->withResponses(
                    (new Responses)->generateRestore($controller)
                )
                ->withTags([
                    Str::plural((new \ReflectionClass($controller::newResource()::newModel()))->getShortName())
                ])
                ->withRequestBody(
                    (new RequestBody)->generateRestore($controller)
                )
                ->generate()
        );
    }

    /**
     * Generate an Operation schema for the "Force Delete" operation.
     *
     * @param  Controller  $controller
     * @return Operation
     */
    public function generateForceDelete(Controller $controller): Operation
    {
        return $controller->generateDocumentationForceDeleteOperation(
            $this
                ->withSummary('Perform a force delete request')
                ->withDescription('Force delete a record')
                ->withResponses(
                    (new Responses)->generateForceDelete($controller)
                )
                ->withTags([
                    Str::plural((new \ReflectionClass($controller::newResource()::newModel()))->getShortName())
                ])
                ->withRequestBody(
                    (new RequestBody)->generateForceDelete($controller)
                )
                ->generate()
        );
    }
}