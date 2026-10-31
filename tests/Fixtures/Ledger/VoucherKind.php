<?php

namespace Lomkit\Rest\Tests\Fixtures\Ledger;

enum VoucherKind: string
{
    case Credit = 'credit';
    case Debit = 'debit';
}
