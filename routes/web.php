<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

// Static shell; all data and permissions come from /api.
Route::view('/admin', 'admin')->name('admin');
