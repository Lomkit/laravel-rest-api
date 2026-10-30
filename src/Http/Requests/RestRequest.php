<?php

namespace Lomkit\Rest\Http\Requests;

use Illuminate\Contracts\Validation\Factory as ValidationFactory;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Arr;
use Illuminate\Validation\Validator;
use Lomkit\Rest\Authorization\Authorizer;
use Lomkit\Rest\Authorization\Check;
use Lomkit\Rest\Concerns\Resourcable;
use Lomkit\Rest\Exceptions\InvalidControllerException;
use Lomkit\Rest\Http\Controllers\Controller;
use Lomkit\Rest\Http\Requests\Traits\InteractsWithRules;
use Lomkit\Rest\Http\Resource;
use Lomkit\Rest\Http\RestContext;
use Lomkit\Rest\Http\Routing\Endpoint;
use Lomkit\Rest\Validation\CompiledRules;
use Lomkit\Rest\Validation\RuleCompiler;

class RestRequest extends FormRequest
{
    use InteractsWithRules;
    use Resourcable;

    private ?RestContext $context = null;

    private ?CompiledRules $compiled = null;

    public function context(): RestContext
    {
        return $this->context ??= new RestContext($this, $this->container);
    }

    public function endpoint(): ?Endpoint
    {
        return null;
    }

    #[\Override]
    protected function passesAuthorization(): bool
    {
        if (! parent::passesAuthorization()) {
            return false;
        }

        $authorizer = $this->context()->authorizer();

        $this->authorizeEntry($authorizer);
        $authorizer->plan($this->reach());

        return true;
    }

    protected function authorizeEntry(Authorizer $authorizer): void
    {
        $endpoint = $this->endpoint();

        if ($endpoint === null) {
            return;
        }

        $resource = $this->rootResource();

        $authorizer->enter($resource, self::checksOn($resource, $resource->abilities($endpoint, $this)));
    }

    /** @return list<Check> */
    protected function reach(): array
    {
        return $this->compiledRules()->reach;
    }

    /**
     * @param  list<string>  $abilities
     * @return list<Check>
     */
    protected static function checksOn(Resource $resource, array $abilities): array
    {
        return array_map(fn (string $ability): Check => Check::onClass($resource, $ability), array_values(array_unique($abilities)));
    }

    protected function rootResource(): Resource
    {
        return $this->context()->resource($this->controllerResourceClass());
    }

    /** @return array<array-key, mixed> */
    #[\Override]
    public function validationData(): array
    {
        return $this->compiledRules()->data($this->all());
    }

    /** @return array<string, list<mixed>> */
    #[\Override]
    protected function validationRules(): array
    {
        return $this->compiledRules()->rules;
    }

    #[\Override]
    protected function createDefaultValidator(ValidationFactory $factory): ValidatorContract
    {
        $validator = parent::createDefaultValidator($factory);

        if ($validator instanceof Validator) {
            $translated = $validator->getTranslator()->get('validation.attributes');
            $validator->setAttributeNames($this->compiledRules()->attributeNames($validator->customAttributes, is_array($translated) ? Arr::dot($translated) : []));
        }

        return $validator;
    }

    protected function shouldFailOnUnknownFields(): bool
    {
        return false;
    }

    /**
     * @param  string  $attribute
     * @param  array<int, string>  $validateOnly
     */
    #[\Override]
    protected function shouldValidatePrecognitiveAttribute($attribute, $validateOnly): bool
    {
        foreach ($validateOnly as $name) {
            if (preg_match('/^'.str_replace('\*', '[^.]+', preg_quote($name, '/')).'(\..+)?$/', $attribute) === 1) {
                return true;
            }
        }

        return false;
    }

    /** @return class-string<Resource> */
    protected function controllerResourceClass(): string
    {
        $controller = $this->route()?->getControllerClass();

        if ($controller === null || ! is_a($controller, Controller::class, true)) {
            throw InvalidControllerException::missingRoute(static::class);
        }

        return $controller::resourceClass();
    }

    private function compiledRules(): CompiledRules
    {
        return $this->compiled ??= RuleCompiler::compile(parent::validationRules(), $this->all());
    }
}
