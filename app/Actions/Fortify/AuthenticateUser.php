<?php

namespace App\Actions\Fortify;

use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Fortify;

class AuthenticateUser
{
    public function authenticate($request): bool
    {
        $credentials = $request->only('email', 'password');
        $user = \App\Models\User::where(column: 'email', operator: $credentials['email'])->where(column: 'active', operator: true)->first();


        if ($user && Auth::attempt(credentials: $credentials)) {
            return true ;
        }
        throw ValidationException::withMessages(messages: [
            Fortify::username() => ['Auth.failed,These credentials do not match our records.'],
        ]);
    }
}

