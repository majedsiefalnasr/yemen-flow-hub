<?php

namespace Database\Seeders;

use App\Models\ImportRequest;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class NotificationSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            'App\\Notifications\\RequestSubmittedNotification',
            'App\\Notifications\\RequestApprovedNotification',
            'App\\Notifications\\RequestRejectedNotification',
            'App\\Notifications\\RequestReturnedNotification',
            'App\\Notifications\\SwiftUploadRequestedNotification',
            'App\\Notifications\\VotingOpenedNotification',
            'App\\Notifications\\CustomsIssuedNotification',
        ];

        $requests = ImportRequest::query()->orderBy('id')->get();
        if ($requests->isEmpty()) {
            return;
        }

        $users = User::query()->get();
        foreach ($users as $user) {
            if (!fake()->boolean(rand(30, 40))) {
                continue;
            }

            $count = rand(2, 8);
            for ($i = 0; $i < $count; $i++) {
                $request = $requests->random();
                $createdAt = now()->subDays(rand(1, 30))->subHours(rand(0, 23));
                DB::table('notifications')->insert([
                    'id' => (string) Str::uuid(),
                    'type' => $types[array_rand($types)],
                    'notifiable_type' => User::class,
                    'notifiable_id' => $user->id,
                    'data' => json_encode([
                        'request_id' => $request->id,
                        'request_reference' => $request->reference_number,
                        'message_ar' => 'إشعار متعلق بالطلب',
                        'message_en' => 'Workflow notification',
                    ], JSON_UNESCAPED_UNICODE),
                    'read_at' => fake()->boolean(40) ? $createdAt->copy()->addHours(rand(1, 48)) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }
}
