<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Lomkit\Rest\Console\Generation\BaseClass;
use Lomkit\Rest\Console\Generation\ResourceIndex;

final readonly class AppNamespace
{
    private function __construct(public string $root, private string $path, private string $basePath) {}

    public static function of(Application $app): self
    {
        return new self($app->getNamespace(), $app->path(), $app->basePath());
    }

    public function models(): string
    {
        return is_dir($this->path.'/Models') ? $this->root.'Models\\' : $this->root;
    }

    public function baseResource(): BaseClass
    {
        return new BaseClass($this->root.'Rest\Resources\Resource', 'rest:base-resource');
    }

    public function baseController(): BaseClass
    {
        return new BaseClass($this->root.'Rest\Controllers\Controller', 'rest:base-controller');
    }

    public function resources(): ResourceIndex
    {
        return new ResourceIndex($this->path.'/Rest/Resources', $this->root.'Rest\Resources\\');
    }

    public function pathOf(string $class): string
    {
        return $this->path.'/'.str_replace('\\', '/', Str::after($class, $this->root)).'.php';
    }

    public function policies(): string
    {
        return $this->display($this->path.'/Policies');
    }

    public function display(string $path): string
    {
        return str_starts_with($path, $this->basePath.'/') ? Str::after($path, $this->basePath.'/') : $path;
    }
}
