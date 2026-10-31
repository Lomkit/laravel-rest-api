<?php

namespace Lomkit\Rest\Text;

interface TextSearch
{
    public function capabilities(TextTarget $target): TextCapabilities;
}
