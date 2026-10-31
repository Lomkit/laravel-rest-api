<?php

namespace Lomkit\Rest\Console\QuickStart;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Illuminate\Support\Arr;
use Symfony\Component\Console\Formatter\OutputFormatter;

final readonly class Outro
{
    private const string HAS_API_TOKENS = 'Laravel\Sanctum\HasApiTokens';

    /** @param  list<PlannedFile>  $created */
    public function __construct(
        private QuickStartPlan $plan,
        private array $created,
        private AppNamespace $app,
        private string $user,
        private string $url,
    ) {}

    public function render(OutputStyle $output, Factory $components, ?string $search): void
    {
        $output->newLine();
        $this->write($output, $this->remarks());

        if (! $this->userHasApiTokens()) {
            $components->warn('Add '.self::HAS_API_TOKENS." to {$this->user}.");
        }

        $output->writeln('  Next:');

        foreach ((new FirstRequest($this->user, $this->url, $search))->commands() as $command) {
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
            $output->writeln('  '.OutputFormatter::escape($line));
        }
    }

    /** @return list<string> */
    private function remarks(): array
    {
        return array_merge(...array_map(fn (PlannedFile $file): array => $file->remarks, $this->created));
    }

    private function userHasApiTokens(): bool
    {
        return class_exists($this->user) && in_array(self::HAS_API_TOKENS, class_uses_recursive($this->user), true);
    }

    /** @return list<string> */
    private function policyReminder(): array
    {
        $policies = array_values(array_map(
            fn (PlannedFile $policy): string => class_basename($policy->class),
            array_filter($this->plan->policies, fn (PlannedFile $policy): bool => in_array($policy, $this->created, true)),
        ));

        if ($policies === []) {
            return [];
        }

        $verbs = count($policies) === 1 ? 'lets signed-in users read and refuses' : 'let signed-in users read and refuse';

        return [Arr::join($policies, ', ', ' and ')." {$verbs} every write. Edit {$this->app->policies()} before production."];
    }
}
