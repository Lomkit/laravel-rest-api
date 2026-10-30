<?php

namespace Lomkit\Rest\Tests;

use Closure;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use ReflectionClass;

abstract class ScaffoldingTestCase extends PackageTestCase
{
    private const array SKELETON_FILES = ['artisan', 'composer.json', 'bootstrap/app.php', 'bootstrap/providers.php', 'bootstrap/.testbench-default-skeleton'];

    private const array SKELETON_DIRECTORIES = ['config', 'database', 'lang'];

    private const array EMPTY_DIRECTORIES = ['app/Models', 'bootstrap/cache', 'routes', 'storage/logs', 'storage/framework/cache', 'storage/framework/sessions', 'storage/framework/views'];

    private static ?string $skeletonPath = null;

    private string $workspace;

    private Closure $autoloader;

    #[\Override]
    public static function applicationBasePath(): string
    {
        return self::$skeletonPath ?? parent::applicationBasePath();
    }

    #[\Override]
    protected function setUp(): void
    {
        $this->workspace = sys_get_temp_dir().'/lomkit-rest-'.bin2hex(random_bytes(8));
        $skeletonPath = $this->workspace.'/laravel';
        $this->copySkeleton(parent::applicationBasePath(), $skeletonPath);
        self::$skeletonPath = $skeletonPath;

        $this->autoloader = static function (string $class) use ($skeletonPath): void {
            $path = $skeletonPath.'/app/'.str_replace('\\', '/', Str::after($class, 'App\\')).'.php';

            if (str_starts_with($class, 'App\\') && is_file($path)) {
                require $path;
            }
        };
        spl_autoload_register($this->autoloader);

        parent::setUp();
    }

    #[\Override]
    protected function tearDown(): void
    {
        parent::tearDown();

        spl_autoload_unregister($this->autoloader);
        (new Filesystem)->deleteDirectory($this->workspace);
        self::$skeletonPath = null;
    }

    protected function loadGeneratedClass(string $class): void
    {
        $path = app_path(str_replace('\\', '/', Str::after($class, 'App\\')).'.php');
        $this->assertFileExists($path);

        $this->assertTrue(class_exists($class), "{$class} cannot be loaded.");
        $this->assertSame(
            realpath($path),
            realpath((string) (new ReflectionClass($class))->getFileName()),
            "{$class} was declared by another test: give the generated class a name of its own.",
        );
    }

    protected function writeModel(string $class, ?string $body = null): void
    {
        $path = app_path(str_replace('\\', '/', Str::after($class, 'App\\')).'.php');

        (new Filesystem)->ensureDirectoryExists(dirname($path));
        file_put_contents($path, sprintf(
            "<?php\n\nnamespace %s;\n\nuse Illuminate\Database\Eloquent\Model;\n\nclass %s extends Model%s\n",
            Str::beforeLast($class, '\\'),
            class_basename($class),
            $body === null ? ' {}' : "\n{\n    {$body}\n}",
        ));
    }

    private function copySkeleton(string $from, string $to): void
    {
        $files = new Filesystem;

        foreach (self::SKELETON_FILES as $file) {
            $files->ensureDirectoryExists(dirname("{$to}/{$file}"));
            $files->copy("{$from}/{$file}", "{$to}/{$file}");
        }

        foreach (self::SKELETON_DIRECTORIES as $directory) {
            $files->copyDirectory("{$from}/{$directory}", "{$to}/{$directory}");
        }

        foreach (self::EMPTY_DIRECTORIES as $directory) {
            $files->ensureDirectoryExists("{$to}/{$directory}");
        }
    }
}
