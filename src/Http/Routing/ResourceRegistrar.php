<?php

namespace Lomkit\Rest\Http\Routing;

use Illuminate\Routing\ResourceRegistrar as BaseResourceRegistrar;
use Lomkit\Rest\Controller;
use Lomkit\Rest\Http\Controllers\EntryController;
use Lomkit\Rest\Http\Resources\Resource;

class ResourceRegistrar extends BaseResourceRegistrar
{
    /**
     * The default actions for a resourceful controller.
     *
     * @var string[]
     */
    protected $resourceDefaults = ['search', 'store', 'show', 'update', 'destroy', 'restore', 'forceDelete'];

    /**
     * The verbs used in the resource URIs.
     *
     * @var array
     */
    protected static $verbs = [
        'search' => 'search',
        'restore' => 'restore',
        'forceDelete' => 'force',
    ];

    /**
     * Add the index method for a resourceful route.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string $controller
     * @param  array  $options
     * @return \Illuminate\Routing\Route
     */
    protected function addResourceSearch($name, $base, $controller, $options)
    {
        $uri = $this->getResourceUri($name).'/'.static::$verbs['search'];

        unset($options['missing']);

        $action = $this->getResourceAction($name, $controller, 'search', $options);

        return $this->router->post($uri, $action);
    }

    /**
     * Add the index method for a resourceful route.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string $controller
     * @param  array  $options
     * @return \Illuminate\Routing\Route
     */
    protected function addResourceRestore($name, $base, $controller, $options)
    {
        $name = $this->getShallowName($name, $options);

        $uri = $this->getResourceUri($name).'/{'.$base.'}'.'/'.static::$verbs['restore'];

        $action = $this->getResourceAction($name, $controller, 'restore', $options);

        return $this->router->post($uri, $action);
    }

    /**
     * Add the index method for a resourceful route.
     *
     * @param  string  $name
     * @param  string  $base
     * @param  string $controller
     * @param  array  $options
     * @return \Illuminate\Routing\Route
     */
    protected function addResourceForceDelete($name, $base, $controller, $options)
    {
        $name = $this->getShallowName($name, $options);

        $uri = $this->getResourceUri($name).'/{'.$base.'}'.'/'.static::$verbs['forceDelete'];

        $action = $this->getResourceAction($name, $controller, 'forceDelete', $options);

        return $this->router->delete($uri, $action);
    }

    /**
     * Route a resource to a controller.
     *
     * @param  string  $name
     * @param  string  $controller
     * @param  array  $options
     * @return \Illuminate\Routing\RouteCollection
     */
    public function register($name, $controller, array $options = [])
    {
        if (!isset($options['soft-deletes'])) {
            $options['except'][] = 'restore';
            $options['except'][] = 'forceDelete';
        } elseif (!empty($options['soft-deletes'])) {
            // Here we will see which route the user wants to specify
            $options['except'] = array_diff(['forceDelete', 'restore'], $options['soft-deletes']);
        }

        return parent::register($name, $controller, $options);
    }
}