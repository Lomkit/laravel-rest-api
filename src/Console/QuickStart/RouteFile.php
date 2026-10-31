<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Support\Arr;
use Lomkit\Rest\Facades\Rest;
use PhpToken;

final readonly class RouteFile
{
    public const string MIDDLEWARE = 'auth:sanctum';

    public function __construct(public string $contents) {}

    public function registers(string $controller): bool
    {
        $source = $this->source();
        $imports = FileImports::read($source);
        $tokens = array_values(FileImports::code($source));

        foreach ($tokens as $index => $token) {
            $isClassConstant = $token->is(FileImports::NAMES)
                && ($tokens[$index + 1] ?? null)?->is(T_DOUBLE_COLON) === true
                && ($tokens[$index + 2] ?? null)?->is(T_CLASS) === true;

            if ($isClassConstant && strcasecmp($imports->resolve($token), $controller) === 0) {
                return true;
            }
        }

        return false;
    }

    public function withResource(string $uri, string $controller): self
    {
        $imports = FileImports::read($this->source());
        $facade = $imports->nameOf(Rest::class);
        $name = $imports->nameOf($controller);
        $missing = array_filter([Rest::class => $facade, $controller => $name], fn (string $short): bool => ! $imports->binds($short) && ! str_starts_with($short, '\\'));
        $contents = $this->withImports(array_keys($missing), $imports);

        return new self(rtrim($contents)."\n\n{$facade}::resource('{$uri}', {$name}::class)\n    ->middleware('".self::MIDDLEWARE."');\n");
    }

    /** @param  list<string>  $classes */
    private function withImports(array $classes, FileImports $imports): string
    {
        if ($classes === []) {
            return $this->contents;
        }

        usort($classes, ImportStatement::compare(...));

        if ($imports->statements === []) {
            return $this->withFirstImports($classes, $imports->headerEnd);
        }

        $last = $imports->statements[array_key_last($imports->statements)];
        $insertions = [];

        foreach ($classes as $class) {
            $next = Arr::first($imports->statements, fn (ImportStatement $statement): bool => $statement->sortsAfter($class));
            $offset = $next === null ? $last->end : $next->start;
            $insertions[$offset] = ($insertions[$offset] ?? '').($next === null ? "\nuse {$class};" : "use {$class};\n");
        }

        krsort($insertions);
        $contents = $this->contents;

        foreach ($insertions as $offset => $statements) {
            $contents = substr_replace($contents, $statements, $offset, 0);
        }

        return $contents;
    }

    /** @param  list<string>  $classes */
    private function withFirstImports(array $classes, int $line): string
    {
        $lines = explode("\n", $this->contents);

        array_splice($lines, $line, 0, [
            '',
            ...array_map(fn (string $class): string => "use {$class};", $classes),
            ...(trim($lines[$line] ?? '') === '' ? [] : ['']),
        ]);

        return implode("\n", $lines);
    }

    /** @return list<PhpToken> */
    private function source(): array
    {
        return array_values(PhpToken::tokenize($this->contents));
    }
}
