<?php

namespace Lomkit\Rest\Tests\Unit\Text;

use Illuminate\Database\Connection;
use Illuminate\Database\Query\Grammars\MySqlGrammar;
use Illuminate\Database\Query\Grammars\PostgresGrammar;
use Illuminate\Database\Query\Grammars\SQLiteGrammar;
use Lomkit\Rest\Query\Expressions\LikeCondition;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class LikeConditionTest extends TestCase
{
    #[Test]
    public function it_escapes_the_special_characters_of_like_in_a_needle(): void
    {
        $this->assertSame('%50!% of !_id !! ![x] \\%', LikeCondition::containing('50% of _id ! [x] \\'));
    }

    #[Test]
    public function it_compiles_a_case_insensitive_like_with_its_escape_character(): void
    {
        $connection = $this->createStub(Connection::class);
        $condition = new LikeCondition('posts.title');

        $this->assertSame('"posts"."title" ilike ? escape \'!\'', $condition->getValue(new PostgresGrammar($connection)));
        $this->assertSame('`posts`.`title` like ? escape \'!\'', $condition->getValue(new MySqlGrammar($connection)));
        $this->assertSame('"posts"."title" like ? escape \'!\'', $condition->getValue(new SQLiteGrammar($connection)));
    }
}
