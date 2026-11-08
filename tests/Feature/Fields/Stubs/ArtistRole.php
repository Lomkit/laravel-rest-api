<?php

namespace Lomkit\Rest\Tests\Feature\Fields\Stubs;

enum ArtistRole: string
{
    case Lead = 'lead';
    case Guest = 'guest';
    case Support = 'support';
}
