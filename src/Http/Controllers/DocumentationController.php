<?php

namespace Lomkit\Rest\Http\Controllers;

class DocumentationController extends \Illuminate\Routing\Controller
{
    public function index()
    {
        return view('rest::index');
    }
}
