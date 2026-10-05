<?php

use App\Http\Controllers\Api\ConversationController;
use App\Http\Controllers\Api\KnowledgeController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\PreferenceController;
use App\Http\Controllers\Api\ReadinessController;
use App\Http\Controllers\Api\StaffTicketController;
use App\Http\Controllers\Api\TicketController;
use App\Http\Controllers\Api\TicketDraftController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Internal JSON API (/api/v1)
|--------------------------------------------------------------------------
|
| Loaded inside the web middleware group by routes/web.php, so endpoints use
| the session and CSRF protection. Responses use App\Support\Http\ApiResponse.
| Business endpoints are added by the plan that owns them.
|
*/

Route::middleware('account')->group(function () {
    Route::post('preferences', [PreferenceController::class, 'update'])->name('preferences.update');

    Route::get('knowledge', [KnowledgeController::class, 'index'])->name('knowledge.index');
    Route::get('knowledge/{article}', [KnowledgeController::class, 'show'])->whereNumber('article')->name('knowledge.show');

    Route::post('conversations', [ConversationController::class, 'store'])->name('conversations.store');
    Route::post('conversations/{conversation}/messages', [ConversationController::class, 'sendMessage'])
        ->middleware('throttle:assistant')
        ->whereUuid('conversation')
        ->name('conversations.messages.store');
    Route::get('conversations/{conversation}/messages', [ConversationController::class, 'history'])->whereUuid('conversation')->name('conversations.messages.index');
    Route::get('conversations/{conversation}/turns/{turn}', [ConversationController::class, 'turn'])->whereUuid(['conversation', 'turn'])->name('conversations.turns.show');

    Route::middleware('throttle:ticket-writes')->group(function () {
        Route::post('ticket-drafts', [TicketDraftController::class, 'store'])->name('ticket-drafts.store');
        Route::patch('ticket-drafts/{draft}', [TicketDraftController::class, 'update'])->whereUuid('draft')->name('ticket-drafts.update');
        Route::post('ticket-drafts/{draft}/confirm', [TicketDraftController::class, 'confirm'])->whereUuid('draft')->name('ticket-drafts.confirm');
        Route::post('tickets/{ticket}/messages', [TicketController::class, 'storeMessage'])->whereUuid('ticket')->name('tickets.messages.store');
        Route::patch('tickets/{ticket}/status', [TicketController::class, 'updateStatus'])->whereUuid('ticket')->name('tickets.status.update');

        Route::prefix('staff')->name('staff.')->group(function () {
            Route::patch('tickets/{ticket}/assignment', [StaffTicketController::class, 'assign'])->whereUuid('ticket')->name('tickets.assignment.update');
            Route::patch('tickets/{ticket}/status', [StaffTicketController::class, 'updateStatus'])->whereUuid('ticket')->name('tickets.status.update');
            Route::post('tickets/{ticket}/messages', [StaffTicketController::class, 'storeMessage'])->whereUuid('ticket')->name('tickets.messages.store');
        });
    });

    Route::get('ticket-drafts/{draft}', [TicketDraftController::class, 'show'])->whereUuid('draft')->name('ticket-drafts.show');
    Route::get('tickets', [TicketController::class, 'index'])->name('tickets.index');
    Route::get('tickets/{ticket}', [TicketController::class, 'show'])->whereUuid('ticket')->name('tickets.show');
    Route::get('tickets/{ticket}/messages', [TicketController::class, 'messages'])->whereUuid('ticket')->name('tickets.messages.index');
    Route::get('staff/tickets/{ticket}/messages', [StaffTicketController::class, 'messages'])->whereUuid('ticket')->name('staff.tickets.messages.index');

    Route::get('admin/readiness', ReadinessController::class)->middleware('can:operations.view')->name('admin.readiness');

    Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('notifications/{notification}/read', [NotificationController::class, 'markRead'])->whereUuid('notification')->name('notifications.read');
});
