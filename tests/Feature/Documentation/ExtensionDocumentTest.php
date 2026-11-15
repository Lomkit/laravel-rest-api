<?php

namespace Lomkit\Rest\Tests\Feature\Documentation;

use Lomkit\Rest\Tests\Fixtures\Festival\ProgramExtension;
use Orchestra\Testbench\Attributes\WithConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

#[WithConfig('rest.extensions', ['program' => ProgramExtension::class])]
class ExtensionDocumentTest extends DocumentationTestCase
{
    private const string INSTRUCTION = '{"type":"object","properties":{"name":{"type":"string","const":"program.only-headliners"},"alias":{"type":"string","description":"The key of this entry among the instructions of the search, in place of the instruction name. It lets a search send one instruction several times.","pattern":"^[A-Za-z_][A-Za-z0-9_]*$","maxLength":255},"fields":{"type":"array","items":{"type":"object","properties":{"name":{"type":"string","const":"min_seats"},"value":{"type":["integer","null"],"description":"Rules: nullable, integer"}},"required":["name"],"additionalProperties":false},"maxItems":100}},"required":["name"],"additionalProperties":false,"title":"ShowInstructionProgram.onlyHeadliners"}';

    #[\Override]
    protected function tearDown(): void
    {
        ProgramExtension::$instances = false;

        parent::tearDown();
    }

    /** @return iterable<string, array{bool}> */
    public static function declarations(): iterable
    {
        yield 'a class' => [false];
        yield 'an instance' => [true];
    }

    #[Test]
    #[DataProvider('declarations')]
    public function it_documents_an_extension_instruction_under_its_public_key(bool $instances): void
    {
        ProgramExtension::$instances = $instances;

        $schemas = $this->document()['components']['schemas'];

        $this->assertSame(self::INSTRUCTION, json_encode($schemas['ShowInstructionProgram.onlyHeadliners'], JSON_UNESCAPED_SLASHES));
        $this->assertSame('{"propertyName":"name","mapping":{"only-featured":"#/components/schemas/ShowInstructionOnlyFeatured","program.only-headliners":"#/components/schemas/ShowInstructionProgram.onlyHeadliners"}}', json_encode($schemas['ShowInstruction']['discriminator'], JSON_UNESCAPED_SLASHES));
        $this->assertSame('[{"$ref":"#/components/schemas/ShowInstructionOnlyFeatured"},{"$ref":"#/components/schemas/ShowInstructionProgram.onlyHeadliners"}]', json_encode($schemas['ShowInstruction']['oneOf'], JSON_UNESCAPED_SLASHES));
        $this->assertSame(['only-featured', 'program.only-headliners'], $schemas['ShowDetails']['properties']['data']['properties']['instructions']['items']['properties']['uriKey']['enum']);
    }

    #[Test]
    public function it_documents_the_capped_total_of_a_resource_that_an_extension_may_cap(): void
    {
        $paginated = $this->document()['components']['schemas']['ShowPaginated'];

        $this->assertSame('{"type":"boolean","description":"True when more rows match than the resource counts: total is then a lower bound"}', json_encode($paginated['properties']['total_capped']));
        $this->assertNotContains('total_capped', $paginated['required']);
    }
}
