<?php

use App\Http\Controllers\Api\DatasetAudioController;
use App\Http\Controllers\Api\DatasetPrepareController;
use App\Http\Controllers\Api\DatasetRenderController;
use App\Http\Controllers\Api\DatasetStatusController;
use App\Http\Controllers\Api\DatasetTimingsController;
use App\Http\Controllers\Api\ReciterController;
use App\Http\Controllers\Api\SurahController;
use App\Http\Controllers\Api\VideoLibraryController;
use Illuminate\Support\Facades\Route;

Route::get('/reciters', [ReciterController::class, 'index']);
Route::get('/surahs', [SurahController::class, 'index']);
Route::get('/datasets/status', [DatasetStatusController::class, 'status']);
Route::post('/datasets/prepare', [DatasetPrepareController::class, 'prepare']);
Route::post('/datasets/audio', [DatasetAudioController::class, 'upload']);
Route::post('/datasets/timings', [DatasetTimingsController::class, 'upload']);

// Rendering API routes
Route::get('/renders', [DatasetRenderController::class, 'index']);
Route::post('/renders', [DatasetRenderController::class, 'store']);
Route::get('/renders/{uuid}', [DatasetRenderController::class, 'show']);

// Videos API routes
Route::get('/videos', [VideoLibraryController::class, 'index']);
Route::get('/videos/{filename}', [VideoLibraryController::class, 'download']);
Route::delete('/videos/{filename}', [VideoLibraryController::class, 'destroy']);
