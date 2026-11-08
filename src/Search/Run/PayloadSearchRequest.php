<?php

namespace Lomkit\Rest\Search\Run;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\ValidationException;
use Lomkit\Rest\Authorization\ActingUser;
use Lomkit\Rest\Http\Requests\SearchRequest;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;

final class PayloadSearchRequest extends SearchRequest
{
    /** @var class-string<Resource> */
    private string $resourceClass;

    private ActingUser $actingUser;

    private ?RestContext $restContext = null;

    /**
     * @param  class-string<Resource>  $resource
     * @param  array<string, mixed>  $body
     */
    public static function of(Container $container, string $resource, array $body, ActingUser $user): self
    {
        $request = self::create('/', 'POST', $body);
        $request->resourceClass = $resource;
        $request->actingUser = $user;
        $request->setContainer($container);
        $request->setUserResolver(fn (): ?Authenticatable => $user->user);

        return $request;
    }

    public function run(): SearchRun
    {
        $this->validateResolved();
        $this->resource($resource = $this->context()->resource($this->resourceClass));

        return SearchRun::of($resource, $this->payload());
    }

    #[\Override]
    public function context(): RestContext
    {
        return $this->restContext ??= new RestContext($this, $this->container, $this->actingUser);
    }

    #[\Override]
    protected function controllerResourceClass(): string
    {
        return $this->resourceClass;
    }

    #[\Override]
    protected function failedValidation(Validator $validator): never
    {
        throw new ValidationException($validator);
    }
}
