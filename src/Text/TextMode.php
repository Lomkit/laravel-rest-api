<?php

namespace Lomkit\Rest\Text;

enum TextMode: string
{
    case Words = 'words';
    case Prefix = 'prefix';
    case Phrase = 'phrase';
    case Websearch = 'websearch';
}
