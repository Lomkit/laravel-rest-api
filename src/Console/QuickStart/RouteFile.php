<?php

namespace Lomkit\Rest\Console\QuickStart;

use Lomkit\Rest\Facades\Rest;
use PhpToken;

final readonly class RouteFile
{
    public const string MIDDLEWARE = 'auth:sanctum';

    private const string CLASS_IMPORT = '/^use [A-Za-z_\\\\][A-Za-z0-9_\\\\]*( as [A-Za-z_][A-Za-z0-9_]*)?;$/';

    public function __construct(public string $contents) {}

    public function registers(string $controller): bool
    {
        $tokens = $this->tokens();
        $imports = FileImports::read($tokens);

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
        $imports = FileImports::read($this->tokens());
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

        $lines = explode("\n", $this->contents);
        $block = array_keys(array_filter($lines, fn (string $line): bool => preg_match(self::CLASS_IMPORT, $line) === 1));
        $start = $block[0] ?? null;
        $length = 0;

        while ($start !== null && in_array($start + $length, $block, true)) {
            $length++;
        }

        $statements = $this->sorted([
            ...($start === null ? [] : array_slice($lines, $start, $length)),
            ...array_map(fn (string $class): string => "use {$class};", $classes),
        ]);

        if ($start !== null) {
            array_splice($lines, $start, $length, $statements);

            return implode("\n", $lines);
        }

        array_splice($lines, $imports->line, 0, [
            ...($imports->followsAnImport ? [] : ['']),
            ...$statements,
            ...(trim($lines[$imports->line] ?? '') === '' ? [] : ['']),
        ]);

        return implode("\n", $lines);
    }

    /**
     * @param  list<string>  $statements
     * @return list<string>
     */
    private function sorted(array $statements): array
    {
        usort($statements, fn (string $first, string $second): int => strcasecmp(str_replace('\\', ' ', $first), str_replace('\\', ' ', $second)));

        return $statements;
    }

    /** @return list<PhpToken> */
    private function tokens(): array
    {
        return array_values(array_filter(PhpToken::tokenize($this->contents), fn (PhpToken $token): bool => ! $token->isIgnorable()));
    }
}
