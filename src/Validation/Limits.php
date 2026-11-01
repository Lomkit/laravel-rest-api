<?php

namespace Lomkit\Rest\Validation;

final readonly class Limits
{
    private const array GROUPS = ['sizes', 'depths', 'totals'];

    public function __construct(
        public ListSizes $sizes,
        public Depths $depths,
        public Totals $totals,
    ) {}

    public static function fromConfig(mixed $config): self
    {
        if (is_array($config)) {
            LimitGroup::refuseUnknown('rest.limits', $config, self::GROUPS);
        }

        return new self(
            ListSizes::from(LimitGroup::of($config, 'sizes', ListSizes::KEYS)),
            Depths::from(LimitGroup::of($config, 'depths', Depths::KEYS)),
            Totals::from(LimitGroup::of($config, 'totals', Totals::KEYS)),
        );
    }
}
