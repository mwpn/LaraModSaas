<?php

use Illuminate\Support\Facades\Route;
use Modules\BaseFeature\Http\Controllers\BaseFeatureController;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::resource('basefeatures', BaseFeatureController::class)->names('basefeature');
});
