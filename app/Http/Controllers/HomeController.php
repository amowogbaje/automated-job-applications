<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class HomeController extends Controller
{
    // GET / — straight to your dashboard, or to sign-in.
    public function __invoke(Request $request)
    {
        return $request->user()
            ? redirect()->route($request->user()->homeRoute())
            : redirect()->route('login');
    }
}
