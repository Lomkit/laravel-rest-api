<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\Arr;

final readonly class Outro
{
    /** @param  list<PlannedFile>  $created */
    public function __construct(
        private QuickStartPlan $plan,
        private array $created,
        private AppNamespace $app,
        private string $user,
        private string $url,
        private ApiGuard $guard,
    ) {}

    public function render(OutputStyle $output, Factory $components, ?string $search): void
    {
        $output->newLine();
        $this->write($output, $this->remarks());

        $trait = $this->guard->kind->package()?->tokensTrait();

        if ($trait !== null && ! $this->userUses($trait)) {
            $components->warn("Add {$trait} to {$this->user}.");
        }

        $output->writeln('  Next:');

        foreach ((new FirstRequest($this->user, $this->url, $search, $this->guard))->commands() as $command) {
            $output->writeln("    {$command}");
        }

        $this->write($output, $this->policyReminder());
    }

    public function renderUnfinished(OutputStyle $output): void
    {
        $lines = [...$this->remarks(), ...$this->policyReminder()];

        if ($lines === []) {
            return;
        }

        $output->newLine();
        $this->write($output, $lines);
    }

    /** @param  list<string>  $lines */
    private function write(OutputStyle $output, array $lines): void
    {
        foreach ($lines as $line) {
            $output->writeln("  {$line}");
        }
    }

    /** @return list<string> */
    private function remarks(): array
    {
        return array_merge(...array_map(fn (PlannedFile $file): array => $file->remarks, $this->created));
    }

    private function userUses(string $trait): bool
    {
        return class_exists($this->user) && in_array($trait, class_uses_recursive($this->user), true);
    }

    /** @return list<string> */
    private function policyReminder(): array
    {
        $created = array_values(array_map(
            fn (PlannedFile $policy): string => $policy->class,
            array_filter($this->plan->policies, fn (PlannedFile $policy): bool => in_array($policy, $this->created, true)),
        ));
        $sentence = $this->sentence($created, 'lets signed-in users do everything', 'let signed-in users do everything');

        return $sentence === null ? [] : ["{$sentence} Edit {$this->app->policies()} before production."];
    }

    /** @param  array<int, string>  $policies */
    private function sentence(array $policies, string $one, string $several): ?string
    {
        if ($policies === []) {
            return null;
        }

        return Arr::join(array_map(class_basename(...), $policies), ', ', ' and ').' '.(count($policies) === 1 ? $one : $several).'.';
    }
}
