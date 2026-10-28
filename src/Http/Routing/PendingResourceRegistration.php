<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Routing\PendingResourceRegistration as BasePendingResourceRegistration;

class PendingResourceRegistration extends BasePendingResourceRegistration
{
    /**
     * Define which routes should allow "soft deletes" routes.
     *
     *
     * @return BasePendingResourceRegistration
     */
    public function withSoftDeletes(array $methods = [])
    {
        $this->options['soft-deletes'] = $methods;

        return $this;
    }
}
