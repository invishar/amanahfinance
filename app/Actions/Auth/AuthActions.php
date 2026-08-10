<?php

namespace App\Actions\Auth;

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthActions
{
    /**
     * @return array{user: User, token: string}
     */
    public function register(array $data): array
    {
        $user = User::create([
            'full_name' => $data['full_name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'password_hash' => Hash::make($data['password']),
        ]);

        // fresh(): created_at is DB useCurrent(), not set by create() itself.
        return $this->issueToken($user->fresh());
    }

    /**
     * @return array{user: User, token: string}
     */
    public function login(array $data): array
    {
        $field = isset($data['email']) ? 'email' : 'phone';
        $user = User::query()->where($field, $data[$field])->first();

        if (! $user || ! Hash::check($data['password'], $user->password_hash)) {
            throw ValidationException::withMessages([
                $field => ['Email/telepon atau kata sandi salah.'],
            ]);
        }

        $user->update(['last_login_at' => now()]);

        return $this->issueToken($user);
    }

    /**
     * @return array{user: User, token: string}
     */
    private function issueToken(User $user): array
    {
        return [
            'user' => $user,
            'token' => $user->createToken('api')->plainTextToken,
        ];
    }
}
