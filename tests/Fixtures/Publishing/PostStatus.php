<?php

namespace Lomkit\Rest\Tests\Fixtures\Publishing;

enum PostStatus: string
{
    case Draft = 'draft';
    case Review = 'review';
    case Published = 'published';
}
