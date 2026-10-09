<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class Login
{
    public function __invoke($_, array $args)
    {
        $email = strtolower(trim((string) ($args['email'] ?? '')));
        $password = (string) ($args['password'] ?? '');
        $user = filter_var($email, FILTER_VALIDATE_EMAIL)
            ? User::where('email', $email)->first()
            : null;

        if (! $user || $password === '' || ! Hash::check($password, $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['Email atau password salah.'],
            ]);
        }

        Auth::guard('web')->login($user);
        request()->session()->regenerate();

        return [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->getRoleNames(), // dari Spatie Permission
            ],
        ];
    }
}