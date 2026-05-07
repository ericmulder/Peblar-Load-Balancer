<?php

use App\Http\Controllers\ApiController;
use App\Http\Controllers\ChargeGoalController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\StrategyController;
use Illuminate\Support\Facades\Route;

Route::get('/', DashboardController::class)->name('dashboard');

Route::prefix('schema')->name('schedule.')->group(function () {
    Route::get('/', [ScheduleController::class, 'index'])->name('index');
    Route::post('/', [ScheduleController::class, 'store'])->name('store');
    Route::put('/{schedule}', [ScheduleController::class, 'update'])->name('update');
    Route::delete('/{schedule}', [ScheduleController::class, 'destroy'])->name('destroy');
    Route::patch('/{schedule}/toggle', [ScheduleController::class, 'toggle'])->name('toggle');
    Route::post('/{schedule}/activate', [ScheduleController::class, 'activate'])->name('activate');
});

Route::prefix('instellingen')->name('settings.')->group(function () {
    Route::get('/', [SettingsController::class, 'index'])->name('index');
    Route::post('/', [SettingsController::class, 'update'])->name('update');
});

Route::get('/geschiedenis', [HistoryController::class, 'index'])->name('history');

Route::get('/strategie', [StrategyController::class, 'index'])->name('strategy');

Route::prefix('laaddoel')->name('goal.')->group(function () {
    Route::post('/', [ChargeGoalController::class, 'store'])->name('store');
    Route::delete('/{goal}', [ChargeGoalController::class, 'destroy'])->name('destroy');
});

Route::prefix('api')->name('api.')->group(function () {
    Route::get('/version', fn() => response()->json(['version' => env('APP_VERSION', 'dev')]))->name('version');
    Route::get('/status', [ApiController::class, 'status'])->name('status');
    Route::post('/balance', [ApiController::class, 'balance'])->name('balance');
    Route::post('/override', [ApiController::class, 'setChargeOverride'])->name('override');
    Route::post('/force-charge', [ApiController::class, 'setForceCharge'])->name('force-charge');
    Route::post('/force-charge/stop', [ApiController::class, 'stopForceCharge'])->name('force-charge.stop');
    Route::post('/toggle', [ApiController::class, 'toggleBalancer'])->name('toggle');
    Route::get('/chart-data', [ApiController::class, 'chartData'])->name('chart-data');
    Route::get('/history', [ApiController::class, 'history'])->name('history-stats');
    Route::post('/vehicle/refresh', [ApiController::class, 'vehicleRefresh'])->name('vehicle.refresh');
});
