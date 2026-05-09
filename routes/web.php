<?php

declare(strict_types=1);

use App\Http\Controllers\Public\HomeController;
use Illuminate\Support\Facades\Route;

// Root domain — acho.test
Route::domain(config('app.base_domain', 'acho.test'))->group(function () {
    Route::get('/', [HomeController::class, 'show']);
});

// www redirect — www.acho.test → acho.test
Route::domain('www.' . config('app.base_domain', 'acho.test'))->group(function () {
    Route::get('{any?}', function () {
        return redirect('http://' . config('app.base_domain', 'acho.test'), 301);
    })->where('any', '.*');
});
