<?php

namespace App\Actions\Admin;

use App\Models\User;

/** T-106 (17-09-2026) — Super Admin corrects an Admin's own name/email/mobile. */
class UpdateAdminUser
{
    public function __invoke(User $user, string $name, string $email, ?string $mobile): User
    {
        $user->update([
            'name' => $name,
            'email' => $email,
            'mobile' => $mobile,
        ]);

        return $user->fresh();
    }
}
