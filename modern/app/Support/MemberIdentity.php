<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class MemberIdentity
{
    public function lock(): void
    {
        DB::table('member_identity_lock')->where('id', 1)->lockForUpdate()->firstOrFail();
    }

    public function conflictsWithUsers(string $login, string $email, bool $localOnly = false, ?int $except = null): bool
    {
        return DB::table('users')->when($localOnly, fn ($query) => $query->whereNull('legacy_id'))
            ->when($except !== null, fn ($query) => $query->where('id', '<>', $except))
            ->where(fn ($query) => $query->whereIn('legacy_login', [$login, $email])->orWhereIn('email', [$login, $email]))
            ->exists();
    }

    public function conflictsWithPending(string $login, string $email, ?int $except = null): bool
    {
        return DB::table('pending_member_registrations')->where('expires_at', '>', now())
            ->when($except !== null, fn ($query) => $query->where('id', '<>', $except))
            ->where(fn ($query) => $query->whereIn('login', [$login, $email])->orWhereIn('email', [$login, $email]))
            ->exists();
    }
}
