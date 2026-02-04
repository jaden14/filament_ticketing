<?php

use Illuminate\Support\Facades\Route;
use App\Livewire\PublicRequestForm;

Route::get('/', function () {
    return redirect('/admin');
});

Route::get('/request', PublicRequestForm::class)
    ->name('public.request');
