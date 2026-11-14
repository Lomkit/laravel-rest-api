<?php

namespace Lomkit\Rest\Http;

use Illuminate\Contracts\Container\Container;
use Lomkit\Rest\Actions\Action;
use Lomkit\Rest\Http\Routing\DeclarationKind;
use Lomkit\Rest\Metrics\Metric;

/** @internal */
final class ResourceDeclarations
{
    /** @var DeclarationList<Action>|null */
    private ?DeclarationList $actions = null;

    /** @var DeclarationList<Metric>|null */
    private ?DeclarationList $metrics = null;

    private ?InstructionList $instructions = null;

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
        return $this->instructions ??= new InstructionList($this->resource->instructions(), $this->resource, $this->container);
    }
}
