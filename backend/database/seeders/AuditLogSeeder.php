<?php

namespace Database\Seeders;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\LoginHistory;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->each(function (User $user): void {
            // 4-12 historical login events spread across the last 45 days
            $count = rand(4, 12);
            $latest = null;

            for ($i = 0; $i < $count; $i++) {
                $at = now()->subDays(rand(1, 45))->subHours(rand(0, 23))->subMinutes(rand(0, 59));
                $ip = fake()->ipv4();
                $ua = fake()->userAgent();

                AuditLog::query()->create([
                    'user_id' => $user->id,
                    'user_role' => $user->role?->value,
                    'action' => AuditAction::LOGIN->value,
                    'subject_type' => User::class,
                    'subject_id' => $user->id,
                    'ip_address' => $ip,
                    'user_agent' => $ua,
                    'metadata' => ['seeded' => true],
                    'created_at' => $at,
                ]);

                LoginHistory::query()->create([
                    'user_id' => $user->id,
                    'ip_address' => $ip,
                    'user_agent' => $ua,
                    'logged_in_at' => $at,
                    'logged_out_at' => fake()->boolean(70) ? $at->copy()->addMinutes(rand(5, 240)) : null,
                ]);

                if (!$latest || $at->greaterThan($latest)) {
                    $latest = $at;
                }
            }

            // ~25% of users also have a recent failed-login event (audit only, no LoginHistory row)
            if (fake()->boolean(25)) {
                $failedAt = now()->subDays(rand(1, 7))->subHours(rand(0, 23));
                AuditLog::query()->create([
                    'user_id' => $user->id,
                    'user_role' => $user->role?->value,
                    'action' => AuditAction::LOGIN_FAILED->value,
                    'subject_type' => User::class,
                    'subject_id' => $user->id,
                    'ip_address' => fake()->ipv4(),
                    'user_agent' => fake()->userAgent(),
                    'metadata' => ['reason' => 'invalid_password', 'seeded' => true],
                    'created_at' => $failedAt,
                ]);
            }

            $user->forceFill(['last_login_at' => $latest])->save();
        });
    }
}
