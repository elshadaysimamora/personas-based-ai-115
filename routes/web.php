<?php

use App\Livewire\Pages\Chat;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DocumentsController;


/*
*Routes untuk guest
*/

Route::middleware(['guest'])->group(function () {
    Route::get('/', function () {
        return redirect()->route('login');
    });
});

/*
*Routes untuk user yang sudah login
*/
Route::middleware([
    'auth:sanctum',
    config('jetstream.auth_session'),
    'verified',
    'active.user',
])->group(function () {
    // Route untuk homepage dan chat
    Route::get('/', function () {
        return redirect()->route('chat');
    })->name('home');

    Route::get('/chat', function () {
        $chat = auth()->user()->conversations()->create([
            'model' => 'gpt-rag'
        ]);
        return redirect()->route('chat.show', $chat);
    })->name('chat');

    Route::get('/chat/{conversation:uuid}', Chat::class)->name('chat.show');
    // routes/web.php
    Route::get('/documents/{document}', [DocumentsController::class, 'show'])->name('documents.show');
});
