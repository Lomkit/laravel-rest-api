<?php

namespace Lomkit\Rest\Tests\Feature\Text;

use Lomkit\Rest\Tests\Fixtures\Publishing\Post;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ContainsSearchTest extends TextSearchTestCase
{
    private Post $deskLamp;

    private Post $floorLamp;

    private Post $vase;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->deskLamp = $this->createPost('Desk lamp', 'Brass and glass shade');
        $this->floorLamp = $this->createPost('Floor lamp', 'Tall reading light');
        $this->vase = $this->createPost('Glass vase');
    }

    #[Test]
    public function it_matches_every_word_in_one_of_the_searchable_fields(): void
    {
        $response = $this->search('contains-posts', ['text' => ['value' => 'lamp glass']]);

        $response->assertOk()->assertJsonPath('data.*.id', [$this->deskLamp->id]);
    }

    #[Test]
    public function it_matches_a_word_in_any_searchable_field(): void
    {
        $response = $this->search('contains-posts', ['text' => ['value' => 'glass']]);

        $response->assertOk()->assertJsonPath('data.*.id', [$this->vase->id, $this->deskLamp->id]);
    }

    #[Test]
    public function it_matches_the_words_in_any_order(): void
    {
        $response = $this->search('contains-posts', ['text' => ['value' => 'lamp desk', 'mode' => 'words']]);

        $response->assertOk()->assertJsonPath('data.*.id', [$this->deskLamp->id]);
    }

    #[Test]
    public function it_matches_a_phrase_as_one_substring(): void
    {
        $phrase = $this->search('contains-posts', ['text' => ['value' => '  desk   lamp ', 'mode' => 'phrase']]);
        $reversed = $this->search('contains-posts', ['text' => ['value' => 'lamp desk', 'mode' => 'phrase']]);

        $phrase->assertOk()->assertJsonPath('data.*.id', [$this->deskLamp->id]);
        $reversed->assertOk()->assertJsonPath('data', []);
    }

    #[Test]
    public function it_ignores_the_case_of_the_text(): void
    {
        $response = $this->search('contains-posts', ['text' => ['value' => 'GLASS Shade']]);

        $response->assertOk()->assertJsonPath('data.*.id', [$this->deskLamp->id]);
    }

    #[Test]
    public function it_ignores_the_words_after_the_tenth(): void
    {
        $response = $this->search('contains-posts', ['text' => ['value' => 'desk lamp brass glass shade desk lamp brass glass shade sofa']]);

        $response->assertOk()->assertJsonPath('data.*.id', [$this->deskLamp->id]);
    }

    #[Test]
    public function it_applies_the_tenth_word(): void
    {
        $response = $this->search('contains-posts', ['text' => ['value' => 'desk lamp brass glass shade desk lamp brass glass floor']]);

        $response->assertOk()->assertJsonPath('data', []);
    }

    #[Test]
    public function it_counts_the_matching_rows_in_the_page(): void
    {
        $response = $this->search('contains-posts', ['text' => ['value' => 'lamp'], 'limit' => 10]);

        $response->assertOk()
            ->assertJsonPath('data.*.id', [$this->floorLamp->id, $this->deskLamp->id])
            ->assertJsonPath('total', 2)
            ->assertJsonPath('per_page', 10);
    }

    /** @param array<string, mixed> $text */
    #[Test]
    #[DataProvider('textsWithoutTerms')]
    public function it_returns_the_normal_list_for_a_text_without_terms(array $text): void
    {
        $response = $this->search('contains-posts', ['text' => $text]);

        $response->assertOk()->assertJsonPath('data.*.id', [$this->vase->id, $this->floorLamp->id, $this->deskLamp->id]);
    }

    /** @return array<string, array{array<string, mixed>}> */
    public static function textsWithoutTerms(): array
    {
        return [
            'an empty value' => [['value' => '']],
            'a value made of spaces' => [['value' => '   ']],
            'a null value' => [['value' => null]],
            'no value' => [[]],
            'punctuation' => [['value' => '!!! ...']],
            'a wildcard' => [['value' => '%']],
            'an underscore in a phrase' => [['value' => '_', 'mode' => 'phrase']],
        ];
    }

    /** @param list<string> $matches */
    #[Test]
    #[DataProvider('specialCharacters')]
    public function it_matches_the_special_characters_of_like_as_they_are(string $text, array $matches): void
    {
        $posts = [];

        foreach (['50% off', '5000 offers', 'snake_case', 'snakeXcase', 'back\\slash', 'backslash', 'bang!', 'bang', '[draft]', 'd'] as $title) {
            $posts[$title] = $this->createPost($title)->id;
        }

        $response = $this->search('contains-posts', ['text' => ['value' => $text], 'sorts' => [['field' => 'id']]]);

        $response->assertOk()->assertJsonPath('data.*.id', array_map(fn (string $title): int => $posts[$title], $matches));
    }

    /** @return array<string, array{string, list<string>}> */
    public static function specialCharacters(): array
    {
        return [
            'a percent sign' => ['50%', ['50% off']],
            'an underscore' => ['snake_case', ['snake_case']],
            'a backslash' => ['back\\slash', ['back\\slash']],
            'an exclamation mark' => ['bang!', ['bang!']],
            'a bracket' => ['[draft]', ['[draft]']],
        ];
    }
}
