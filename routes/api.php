<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

// Vercel has no crontab; Vercel Cron hits this instead (see vercel.json) and
// sends "Authorization: Bearer $CRON_SECRET" automatically when that env var is set.
Route::get('/cron', function (Request $request) {
    abort_unless(
        hash_equals('Bearer '.config('app.cron_secret'), (string) $request->header('Authorization')),
        403
    );

    Artisan::call('schedule:run');

    return response()->noContent();
});
