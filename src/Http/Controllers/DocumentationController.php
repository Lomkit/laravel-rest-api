<?php

namespace Lomkit\Rest\Http\Controllers;

class DocumentationController extends \Illuminate\Routing\Controller
{
    /**
     * Display the documentation index page.
     *
     * @return \Illuminate\Contracts\View\View
     *
     * This method is responsible for rendering and returning the 'index' view,
     * which typically contains the documentation for the application.
     */
    public function index()
    {
        return view('rest::index');
    }
}
