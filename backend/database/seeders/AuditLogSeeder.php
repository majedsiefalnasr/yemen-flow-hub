<?php

namespace Database\Seeders;

use App\Enums\AuditAction;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Seeder;

class AuditLogSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->each(function (User $user): void {
            $at = now()->subDays(rand(1, 15))->subHours(rand(1, 23));
            $user->forceFill(['last_login_at' => $at])->save();

            AuditLog::query()->create([
                'user_id' => $user->id,
                'user_role' => $user->role?->value,
                'action' => AuditAction::LOGIN->value,
                'subject_type' => User::class,
                'subject_id' => $user->id,
                'ip_address' => fake()->ipv4(),
                'user_agent' => 'Seeder/1.0',
                'metadata' => ['seeded' => true],
                'created_at' => $at,
            ]);
        });
    }
}
