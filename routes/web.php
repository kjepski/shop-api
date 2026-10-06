<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Static shell; all data and permissions come from /api.
Route::view('/admin', 'admin')->name('admin');

// Vue panel (replaces /admin once it reaches parity). Every sub-path returns the same shell,
// so Vue Router can render lists, details, create and edit pages on refresh or direct link.
// Keep it last: any web route under /admin-next registered after it would never be reached.
Route::view('/admin-next/{path?}', 'admin-next')->where('path', '.*')->name('admin-next');
