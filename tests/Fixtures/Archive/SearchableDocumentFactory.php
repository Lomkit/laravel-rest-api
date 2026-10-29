<?php

namespace Lomkit\Rest\Tests\Fixtures\Archive;

use Illuminate\Database\Eloquent\Factories\Attributes\UseModel;

#[UseModel(SearchableDocument::class)]
class SearchableDocumentFactory extends DocumentFactory {}
