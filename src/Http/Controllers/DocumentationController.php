<?php

namespace Lomkit\Rest\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Routing\Controller;

class DocumentationController extends Controller
{
    /**
     * Display the documentation index page.
     *
     * @return View
     *
     * This method is responsible for rendering and returning the 'index' view,
     * which typically contains the documentation for the application.
     */
    public function index()
    {
        return view('rest::index');
    }
}
