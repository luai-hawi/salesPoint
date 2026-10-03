<?php

use App\Http\Controllers\Finance\ActivityLogController;
use App\Http\Controllers\Finance\CashDrawerController;
use App\Http\Controllers\Finance\DayCloseController;
use App\Http\Controllers\Finance\TeamSummaryController;
use App\Http\Controllers\ReportsController;
use App\Http\Middleware\PermissionMiddleware;
use App\Http\Middleware\RoleMiddleware;
use Illuminate\Support\Facades\Route;

Route::middleware(['auth', RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant', PermissionMiddleware::class . ':view_financial', 'tier.feature:financial_dashboard'])
    ->group(function () {
        Route::get('/finance/cash-drawer', [CashDrawerController::class, 'index'])->name('finance.cash-drawer.index');
        Route::post('/finance/cash-drawer', [CashDrawerController::class, 'store'])->name('finance.cash-drawer.store');
        Route::delete('/finance/cash-drawer/{cashMovement}', [CashDrawerController::class, 'destroy'])->name('finance.cash-drawer.destroy');
        Route::get('/finance/cash-drawer/export', [CashDrawerController::class, 'export'])->name('finance.cash-drawer.export');
        Route::get('/finance/day-close/print', [DayCloseController::class, 'print'])->name('finance.day-close.print');
    });

// Employees with only "close_day" get a blind cash count (no expected cash, sales or variance).
Route::middleware(['auth', RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant', PermissionMiddleware::class . ':view_financial|close_day', 'tier.feature:financial_dashboard'])
    ->group(function () {
        Route::get('/finance/day-close', [DayCloseController::class, 'index'])->name('finance.day-close.index');
        Route::post('/finance/day-close', [DayCloseController::class, 'store'])->name('finance.day-close.store');
    });

Route::middleware(['auth', RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant', PermissionMiddleware::class . ':view_team_activity', 'tier.feature:financial_dashboard'])
    ->group(function () {
        Route::get('/finance/team-summary', [TeamSummaryController::class, 'index'])->name('finance.team-summary.index');
        Route::get('/shopowner/activity', [ActivityLogController::class, 'index'])->name('shopowner.activity.index');
        Route::get('/shopowner/activity/export', [ActivityLogController::class, 'export'])->name('shopowner.activity.export');
    });

Route::middleware(['auth', RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant', PermissionMiddleware::class . ':view_expenses'])
    ->group(function () {
        Route::put('/shopowner/expenses/{expense}', [\App\Http\Controllers\ShopOwner\ExpenseController::class, 'update'])->name('shopowner.expenses.update');
        Route::get('/shopowner/expenses/export', [\App\Http\Controllers\ShopOwner\ExpenseController::class, 'export'])->name('shopowner.expenses.export');
    });

Route::middleware(['auth', RoleMiddleware::class . ':admin,shop_owner,employee,restaurant,merchant', PermissionMiddleware::class . ':view_reports'])
    ->group(function () {
        Route::get('/reports/print', [ReportsController::class, 'print'])->name('reports.print');
        Route::get('/reports/export', [ReportsController::class, 'export'])->name('reports.export');
    });
