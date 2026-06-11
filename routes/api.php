<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\NotificationController;

Route::post('/notifications/send', [NotificationController::class, 'send'])
    ->middleware('idempotency');

Route::get('/notifications/{recipient}/history', [NotificationController::class, 'history']);
