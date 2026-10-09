<?php

namespace App\GraphQL\Mutations;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Spatie\Permission\Models\Role;

class CreateUser
{
    public function __invoke($_, array $args): User
    {
        $args['name'] = trim((string) ($args['name'] ?? ''));
        $args['email'] = strtolower(trim((string) ($args['email'] ?? '')));

        $validated = Validator::make($args, [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
        ])->validate();

        return DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
            ]);

            Role::findOrCreate('staff', 'web');
            $user->assignRole('staff');

            return $user;
        });
    }
}