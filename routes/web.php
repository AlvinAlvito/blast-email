<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CampaignController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\SenderAccountController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('admin.auth');

Route::middleware('admin.auth')->group(function () {
    Route::get('/', [AdminController::class, 'overview'])->name('admin.overview');
    Route::get('/contacts', [AdminController::class, 'contacts'])->name('admin.contacts');
    Route::get('/contacts/batches/{importBatch}', [AdminController::class, 'contactBatch'])->name('admin.contacts.batch');
    Route::get('/senders', [AdminController::class, 'senders'])->name('admin.senders');
    Route::get('/campaigns', [AdminController::class, 'campaigns'])->name('admin.campaigns');
    Route::get('/analytics', [AdminController::class, 'analytics'])->name('admin.analytics');
    Route::get('/campaigns/{campaign}', [AdminController::class, 'campaignDetail'])->name('admin.campaigns.show');

    Route::get('/imports/template', [ImportController::class, 'downloadTemplate'])->name('imports.template');
    Route::post('/imports', [ImportController::class, 'store'])->name('imports.store');
    Route::post('/sender-accounts', [SenderAccountController::class, 'store'])->name('senders.store');
    Route::get('/sender-accounts/{senderAccount}/edit', [SenderAccountController::class, 'edit'])->name('senders.edit');
    Route::put('/sender-accounts/{senderAccount}', [SenderAccountController::class, 'update'])->name('senders.update');
    Route::delete('/sender-accounts/{senderAccount}', [SenderAccountController::class, 'destroy'])->name('senders.destroy');
    Route::patch('/sender-accounts/{senderAccount}/toggle', [SenderAccountController::class, 'toggle'])->name('senders.toggle');
    Route::post('/sender-accounts/{senderAccount}/test', [SenderAccountController::class, 'test'])->name('senders.test');
    Route::post('/campaigns', [CampaignController::class, 'store'])->name('campaigns.store');
    Route::post('/campaigns/{campaign}/retry-failed', [CampaignController::class, 'retryFailed'])->name('campaigns.retry-failed');
    Route::post('/campaigns/{campaign}/pause', [CampaignController::class, 'pause'])->name('campaigns.pause');
    Route::post('/campaigns/{campaign}/resume', [CampaignController::class, 'resume'])->name('campaigns.resume');
    Route::post('/campaigns/{campaign}/stop', [CampaignController::class, 'stop'])->name('campaigns.stop');
});
