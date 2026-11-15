<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Fields\FieldSet;
use Lomkit\Rest\Http\Routing\DeclarationKind;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Metrics\Metric;
use WeakMap;

/** @internal */
final class ResourceDeclarations
{
    /** @var DeclarationList<Action>|null */
    private ?DeclarationList $actions = null;

    /** @var DeclarationList<Metric>|null */
    private ?DeclarationList $metrics = null;

    private ?InstructionList $instructions = null;

    /** @var WeakMap<Action|Instruction, FieldSet>|null */
    private ?WeakMap $inputs = null;

    public function __construct(private readonly Resource $resource, private readonly Container $container) {}

    /** @return DeclarationList<Action> */
    public function actions(): DeclarationList
    {
        return $this->actions ??= new DeclarationList(DeclarationKind::Action, $this->resource->actions(), $this->resource, $this->container);
    }

    /** @return DeclarationList<Metric> */
    public function metrics(): DeclarationList
    {
        return $this->metrics ??= new DeclarationList(DeclarationKind::Metric, $this->resource->metrics(), $this->resource, $this->container);
    }

    public function instructions(): InstructionList
    {
        return $this->instructions ??= new InstructionList($this->resource->instructions(), fn (): array => ExtensionInstructions::of($this->resource), $this->resource, $this->container);
    }

    public function inputFields(Action|Instruction $declaration): FieldSet
    {
        $this->inputs ??= new WeakMap;

        return $this->inputs[$declaration] ??= FieldSet::forInputs($declaration->fields(), $declaration::class.'::fields()');
    }
}
