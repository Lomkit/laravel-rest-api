<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Filesystem\Filesystem;
use Lomkit\Rest\Console\Generation\ClassDraft;
use ReflectionClass;

final readonly class PlannedFile
{
    /** @param  list<string>  $remarks */
    private function __construct(
        public string $class,
        public string $path,
        private ?string $contents,
        private string $detail = '',
        public array $remarks = [],
    ) {}

    /** @param  list<string>  $remarks */
    public static function draft(ClassDraft $draft, AppNamespace $app, string $detail = '', array $remarks = []): self
    {
        return new self($draft->class, $app->pathOf($draft->class), $draft->render(), $detail, $remarks);
    }

    /** @param  class-string  $class */
    public static function existing(string $class): self
    {
        return new self($class, (string) (new ReflectionClass($class))->getFileName(), null);
    }

    public function isNew(Filesystem $files): bool
    {
        return $this->contents !== null && ! $files->exists($this->path);
    }

    public function write(Filesystem $files): string
    {
        if ($this->contents === null || ! $this->isNew($files)) {
            return 'kept';
        }

        $files->ensureDirectoryExists(dirname($this->path));
        $files->put($this->path, $this->contents);

        return trim("created {$this->detail}");
    }
}
