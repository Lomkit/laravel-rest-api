<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Routing\PendingResourceRegistration as BasePendingResourceRegistration;

class PendingResourceRegistration extends BasePendingResourceRegistration
{
    /**
     * Define which routes should allow "soft deletes" routes.
     *
     * @param array $methods
     *
     * @return \Illuminate\Routing\PendingResourceRegistration
     */
    public function withSoftDeletes(array $methods = [])
    {
        $this->options['soft-deletes'] = $methods;

        return $this;
    }
}
