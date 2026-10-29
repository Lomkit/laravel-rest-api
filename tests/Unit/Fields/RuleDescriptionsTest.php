<?php

namespace Lomkit\Rest\Tests\Unit\Fields;

use Closure;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Lomkit\Rest\Fields\Details\RuleDescriptions;
use Lomkit\Rest\Tests\Fixtures\Publishing\PostStatus;
use Lomkit\Rest\Tests\Unit\Fields\Stubs\StartsWithCapital;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class RuleDescriptionsTest extends TestCase
{
    #[Test]
    public function it_splits_a_pipe_separated_rule_string(): void
    {
        $this->assertSame(['required', 'max:255'], (new RuleDescriptions('required|max:255'))->toArray());
    }

    #[Test]
    public function it_keeps_the_strings_of_a_rule_list_as_declared(): void
    {
        $this->assertSame(
            ['required', 'regex:/^(draft|review)$/', 'max:20'],
            (new RuleDescriptions(['required', 'regex:/^(draft|review)$/', 'max:20']))->toArray(),
        );
    }

    #[Test]
    public function it_casts_a_stringable_rule_to_its_string(): void
    {
        $this->assertSame(
            ['in:"draft","review"', 'unique:posts,title,NULL,id', 'exists:posts,id', 'date|after:2026-01-01'],
            (new RuleDescriptions([Rule::in(['draft', 'review']), Rule::unique('posts', 'title'), Rule::exists('posts', 'id'), Rule::date()->after('2026-01-01')]))->toArray(),
        );
    }

    #[Test]
    public function it_names_other_rule_objects_by_their_snake_class_name(): void
    {
        $this->assertSame(
            ['enum', 'password', 'starts_with_capital'],
            (new RuleDescriptions([Rule::enum(PostStatus::class), Password::min(8), new StartsWithCapital]))->toArray(),
        );
    }

    #[Test]
    public function it_leaves_closures_out(): void
    {
        $closure = function (string $attribute, mixed $value, Closure $fail): void {};

        $this->assertSame(['string', 'max:20'], (new RuleDescriptions(['string', $closure, 'max:20']))->toArray());
    }

    #[Test]
    public function it_describes_an_array_form_rule_like_its_string_form(): void
    {
        $this->assertSame(
            ['max:3', 'in:draft,review', 'required'],
            (new RuleDescriptions([['max', 3], ['in', 'draft', 'review'], ['required']]))->toArray(),
        );
    }

    #[Test]
    public function it_leaves_out_rules_that_describe_as_empty(): void
    {
        $this->assertSame(['required'], (new RuleDescriptions([Rule::requiredIf(false), '', Rule::requiredIf(true)]))->toArray());
        $this->assertSame([], (new RuleDescriptions(''))->toArray());
    }
}
