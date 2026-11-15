<?php

namespace Lomkit\Rest\Actions;

enum ActionTransaction
{
    case Chunk;
    case Whole;
    case Row;
    case None;
}
