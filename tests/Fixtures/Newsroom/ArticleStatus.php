<?php

namespace Lomkit\Rest\Tests\Fixtures\Newsroom;

enum ArticleStatus: string
{
    case Draft = 'draft';
    case Published = 'published';
}
