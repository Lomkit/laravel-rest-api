<?php

namespace Lomkit\Rest\Validation;

final readonly class Limits
{
    public function __construct(
        public ListSizes $sizes,
        public Depths $depths,
        public Totals $totals,
    ) {}

    public static function fromConfig(mixed $config): self
    {
        return new self(
            ListSizes::from(LimitGroup::of($config, 'sizes')),
            Depths::from(LimitGroup::of($config, 'depths')),
            Totals::from(LimitGroup::of($config, 'totals')),
        );
    }
}
