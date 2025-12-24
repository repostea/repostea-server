<?php

declare(strict_types=1);

namespace App\Http\Controllers;

final class HomeController extends Controller
{
    public function index(): \Illuminate\Contracts\Support\Renderable
    {
        return view('home');
    }
}
