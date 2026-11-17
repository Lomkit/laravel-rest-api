<?php

namespace Lomkit\Rest\Tests\Feature\Text\Engine\Typesense;

use Lomkit\Rest\Tests\Feature\Text\Engine\ListsAnIndex;
use Lomkit\Rest\Tests\Fixtures\Ledger\Voucher;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Typesense\Exceptions\TypesenseClientError;

#[Group('scout')]
#[Group('typesense')]
class TypesenseListTest extends TypesenseTestCase
{
    use ListsAnIndex;

    #[Test]
    public function it_relies_on_typesense_refusing_a_sort_on_the_document_id(): void
    {
        $this->expectException(TypesenseClientError::class);

        Voucher::search('*')->orderBy('id', 'desc')->raw();
    }
}
