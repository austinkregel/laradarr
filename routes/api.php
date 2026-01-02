<?php

use App\Http\Controllers\RecommendationController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/user', function (Request $request) {
        return $request->user();
    });

    Route::get('/recommendations/shows', [RecommendationController::class, 'showsApi']);
    Route::get('/recommendations/movies', [RecommendationController::class, 'moviesApi']);
    Route::get('/shows/{show}/similar', [RecommendationController::class, 'similar']);
    Route::get('/movies/{movie}/similar', [RecommendationController::class, 'similarMovies']);
});
