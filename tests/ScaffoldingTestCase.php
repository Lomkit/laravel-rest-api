<?php

namespace Lomkit\Rest\Tests;

use Illuminate\Filesystem\Filesystem;

abstract class ScaffoldingTestCase extends PackageTestCase
{
    private static ?string $skeletonPath = null;

    public static function applicationBasePath(): string
    {
        return self::$skeletonPath ?? parent::applicationBasePath();
    }

    protected function setUp(): void
    {
        $skeletonPath = sys_get_temp_dir().'/lomkit-rest-'.bin2hex(random_bytes(8));
        $this->copySkeleton(parent::applicationBasePath(), $skeletonPath);
        self::$skeletonPath = $skeletonPath;

        parent::setUp();
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        (new Filesystem)->deleteDirectory((string) self::$skeletonPath);
        self::$skeletonPath = null;
    }

    private function copySkeleton(string $from, string $to): void
    {
        $files = new Filesystem;
        $files->ensureDirectoryExists($to);

        foreach ($files->directories($from) as $directory) {
            if (basename($directory) !== 'vendor') {
                $files->copyDirectory($directory, $to.'/'.basename($directory));
            }
        }

        foreach ($files->files($from, true) as $file) {
            $files->copy($file->getPathname(), $to.'/'.$file->getFilename());
        }
    }
}
