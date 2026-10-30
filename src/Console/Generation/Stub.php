<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Filesystem\Filesystem;
use Lomkit\Rest\Exceptions\InvalidStubException;

enum Stub: string
{
    case Action = 'action';
    case BaseController = 'base-controller';
    case BaseResource = 'base-resource';
    case Controller = 'controller';
    case Instruction = 'instruction';
    case Resource = 'resource';
    case Response = 'response';

    public function path(): string
    {
        $published = base_path('stubs/'.$this->publishedName());

        return is_file($published) ? $published : $this->packagePath();
    }

    public function packagePath(): string
    {
        return dirname(__DIR__).'/stubs/'.$this->value.'.stub';
    }

    public function publishedName(): string
    {
        return "rest.{$this->value}.stub";
    }

    /** @param  array<string, string|null>  $values */
    public function fill(array $values): string
    {
        $path = $this->path();
        $contents = (new Filesystem)->get($path);

        foreach ($this->placeholders() as $placeholder) {
            if (! str_contains($contents, $placeholder)) {
                throw InvalidStubException::missingPlaceholder($path, $placeholder);
            }
        }

        $contents = $this->replace($contents, array_filter($values, is_string(...)));

        foreach (array_keys($values, null, true) as $line) {
            $contents = $this->withoutLinesHolding($contents, $line);
        }

        return $contents;
    }

    /** @return list<string> */
    private function placeholders(): array
    {
        return match ($this) {
            self::Controller => ['{{ namespace }}', '{{ class }}', '{{ namespacedResource }}', '{{ resource }}'],
            self::Resource => ['{{ namespace }}', '{{ class }}', '{{ namespacedModel }}', '{{ model }}', '{{ imports }}', '{{ fields }}', '{{ relations }}'],
            default => ['{{ namespace }}', '{{ class }}'],
        };
    }

    /** @param  array<string, string>  $values */
    private function replace(string $contents, array $values): string
    {
        $lines = explode("\n", $contents);

        foreach ($lines as $index => $line) {
            $indentation = substr($line, 0, strspn($line, " \t"));
            $lines[$index] = strtr($line, array_map(
                fn (string $value): string => preg_replace('/\n(?=.)/', "\n{$indentation}", $value) ?? $value,
                $values,
            ));
        }

        return implode("\n", $lines);
    }

    private function withoutLinesHolding(string $contents, string $needle): string
    {
        $kept = [];
        $dropBlankLine = false;

        foreach (explode("\n", $contents) as $line) {
            if (str_contains($line, $needle)) {
                $previous = trim((string) end($kept));
                $dropBlankLine = $previous === '' || str_ends_with($previous, '{');

                continue;
            }

            if ($dropBlankLine && trim($line) === '') {
                $dropBlankLine = false;

                continue;
            }

            $dropBlankLine = false;
            $kept[] = $line;
        }

        $contents = str_replace("\n{\n}", ' {}', implode("\n", $kept));

        return preg_replace('/\[\n\s*\]/', '[]', $contents) ?? $contents;
    }
}
