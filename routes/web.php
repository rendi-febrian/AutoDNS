<?php

use App\Http\Controllers\CloudflareAccountController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DnsController;
use App\Http\Controllers\TrackedDomainController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return to_route('dashboard');
});

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // Cloudflare Accounts
    Route::prefix('cloudflare')->name('cloudflare.')->group(function () {
        Route::get('/accounts', [CloudflareAccountController::class, 'index'])->name('accounts.index');
        Route::post('/accounts', [CloudflareAccountController::class, 'store'])->name('accounts.store');
        Route::delete('/accounts/{cloudflareAccount}', [CloudflareAccountController::class, 'destroy'])->name('accounts.destroy');
        Route::get('/accounts/{cloudflareAccount}/zones', [CloudflareAccountController::class, 'zones'])->name('accounts.zones');
        Route::get('/accounts/{cloudflareAccount}/zones/{zoneId}/sync-records', [CloudflareAccountController::class, 'syncZoneRecords'])->name('accounts.sync-zone-records');
    });

    // Zones & DNS Records
    Route::get('/zones/{zone}/records', [DnsController::class, 'records'])->name('zones.records');
    Route::post('/zones/{zone}/records/sync', [DnsController::class, 'syncRecords'])->name('zones.records.sync');
    Route::post('/zones/{zone}/records/create', [DnsController::class, 'createRecord'])->name('zones.records.create');

    // Tracked Domains
    Route::prefix('tracked-domains')->name('tracked-domains.')->group(function () {
        Route::get('/', [TrackedDomainController::class, 'index'])->name('index');
        Route::post('/', [TrackedDomainController::class, 'store'])->name('store');
        Route::put('/{trackedDomain}', [TrackedDomainController::class, 'update'])->name('update');
        Route::delete('/{trackedDomain}', [TrackedDomainController::class, 'destroy'])->name('destroy');
        Route::post('/sync-all', [TrackedDomainController::class, 'syncAll'])->name('sync-all');
        Route::post('/import-config', [TrackedDomainController::class, 'importConfig'])->name('import-config');
        Route::post('/resolve-ips', [TrackedDomainController::class, 'resolveIps'])->name('resolve-ips');
    });

    // DNS Update (Manual)
    Route::prefix('dns')->name('dns.')->group(function () {
        Route::get('/update', [DnsController::class, 'updateView'])->name('update');
        Route::post('/update/single', [DnsController::class, 'updateSingle'])->name('update.single');
        Route::post('/update/bulk', [DnsController::class, 'updateBulk'])->name('update.bulk');
    });

    // Logs
    Route::get('/logs', [DnsController::class, 'logs'])->name('logs');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__ . '/auth.php';
