<?php

namespace App\Actions\Fortify;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthenticateUser
{
    /**
     * Authenticate the incoming request.
     *
     * Extends the default Fortify authentication to:
     * - Block inactive users with a specific error message (FR-1.2, Business Rules)
     *
     * @throws ValidationException
     */
    public function __invoke(Request $request): ?User
    {
        /** @var User|null $user */
        $user = User::where('email', $request->input('email'))->first();

        if (! $user || ! Hash::check($request->input('password'), $user->password)) {
            return null;
        }

        if ($user->status === UserStatus::Inactive) {
            throw ValidationException::withMessages([
                'email' => [__('Akun Anda telah dinonaktifkan. Silakan hubungi administrator.')],
            ]);
        }

        return $user;
    }
}
