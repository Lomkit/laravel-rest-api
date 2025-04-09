<?php

namespace Lomkit\Rest\Console;

trait ResolvesStubPath
{
    /**
     * Resolve the fully-qualified path to the stub.
     *
     * @param string $stub
     *
     * @return string
     */
    protected function resolveStubPath(string $stub): string
    {
        $customPath = str_replace('rest/', '', $stub);
        $relativePath = ltrim($customPath, '/');

        $publishedPath = base_path($relativePath);
        if (file_exists($publishedPath)) {
            return $publishedPath;
        }

        $baseStubPath = $this->laravel->basePath(trim($stub, '/'));
        if (file_exists($baseStubPath)) {
            return $baseStubPath;
        }

        return __DIR__ . '/../Console/stubs/' . basename($customPath);
    }
}
