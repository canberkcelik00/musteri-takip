<?php

use Illuminate\Support\Facades\Route;

// A view route (not a closure) so that `route:cache` can cache the routes.
Route::view('/', 'welcome');
