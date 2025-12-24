<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

final class ManifestoController extends Controller
{
    public function index(Request $request): View
    {
        return view('manifesto');
    }
}
