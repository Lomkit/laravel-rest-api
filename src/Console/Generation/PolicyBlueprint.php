<?php

namespace Lomkit\Rest\Console\Generation;

use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

final readonly class PolicyBlueprint
{
    private const array SOFT_DELETE_ABILITIES = ['restore', 'forceDelete'];

    private function __construct(private ImportSet $imports, private string $model, private string $user) {}

    public static function for(string $policy, string $model, string $user): self
    {
        $imports = ImportSet::forPolicy($policy)
            ->with($model, 'Model')
            ->with($user, 'User');

        return new self($imports, $model, $user);
    }

    /** @return array<string, string|null> */
    public function replacements(): array
    {
        return [
            '{{ namespacedModel }}' => $this->imports->statement($this->model),
            '{{ namespacedUserModel }}' => $this->model === $this->user ? null : $this->imports->statement($this->user),
            '{{ model }}' => $this->imports->name($this->model),
            '{{ modelVariable }}' => $this->variable(),
            '{{ user }}' => $this->imports->name($this->user),
            '{{ softDeleteMethods }}' => $this->softDeleteMethods(),
        ];
    }

    private function variable(): string
    {
        $variable = Str::camel(class_basename($this->model));

        return $variable === 'user' ? 'model' : $variable;
    }

    private function softDeleteMethods(): ?string
    {
        if (! in_array(SoftDeletes::class, class_uses_recursive($this->model), true)) {
            return null;
        }

        $parameters = "{$this->imports->name($this->user)} \$user, {$this->imports->name($this->model)} \${$this->variable()}";
        $methods = array_map(
            fn (string $ability): string => "public function {$ability}({$parameters}): bool\n{\n    return false;\n}",
            self::SOFT_DELETE_ABILITIES,
        );

        return "\n".Declaration::indent(implode("\n\n", $methods));
    }
}
