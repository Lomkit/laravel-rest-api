<?php

namespace Lomkit\Rest\Text;

enum TextBackend
{
    case Query;
    case Engine;
    case None;
}
