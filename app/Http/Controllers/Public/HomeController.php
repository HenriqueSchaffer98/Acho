<?php

declare(strict_types=1);

namespace App\Http\Controllers\Public;

use Illuminate\Routing\Controller;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function show(): View
    {
        return view('public.home');
    }
}
