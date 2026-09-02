<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CommunicationViewController;

Route::get('/', function () {
    return view('welcome');
});

// Standalone email-view page for a sent communication (WeConnectU
// download-mail-new.php parity). Public but guarded by an unguessable token.
Route::get('/communications/view/{token}', [CommunicationViewController::class, 'show'])
    ->name('communications.view');
