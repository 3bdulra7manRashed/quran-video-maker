<?php

use App\Http\Controllers\Api\DatasetStatusController;
use Illuminate\Support\Facades\Route;

Route::get('/datasets/status', [DatasetStatusController::class, 'status']);
