<?php

namespace Lomkit\Rest\Tests\Fixtures\Helpdesk;

enum ChannelCode: string
{
    case Email = 'email';
    case Phone = 'phone';
}
