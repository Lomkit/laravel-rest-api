<?php

namespace Lomkit\Rest\Tests\Feature\Declarations\Stubs;

use Illuminate\Database\Eloquent\Builder;
use Lomkit\Rest\Fields\Text;
use Lomkit\Rest\Instructions\Instruction;
use Lomkit\Rest\Instructions\InstructionFields;

class DeskTagMatch extends Instruction
{
    public ?string $name = 'Tag match';

    public function __construct(private readonly TicketJournal $journal)
    {
        DeclarationLog::$built[] = static::class;
    }

    #[\Override]
    public function fields(): array
    {
        return [Text::make('word')->rules('alpha')->default('Hardware')];
    }

    #[\Override]
    public function handle(InstructionFields $fields, Builder $query): void
    {
        $word = $fields->string('word', required: true);
        $this->journal->write("matched tag {$word}");

        $query->where('tags.name', 'like', "%{$word}%");
    }
}
