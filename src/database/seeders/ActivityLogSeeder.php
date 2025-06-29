<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Activitylog\Models\Activity;

class ActivityLogSeeder extends Seeder
{
    public function run()
    {
        // Create test users
        $users = User::factory(10)->create();

        // Create various types of activities
        foreach ($users as $index => $user) {
            // User created activity
            activity()
                ->causedBy($user)
                ->performedOn($user)
                ->event('created')
                ->log('User account created');

            // User updated activity with properties
            activity()
                ->causedBy($user)
                ->performedOn($user)
                ->event('updated')
                ->withProperties([
                    'old' => [
                        'name' => 'Old Name ' . $index,
                        'email' => 'old' . $index . '@example.com'
                    ],
                    'attributes' => [
                        'name' => $user->name,
                        'email' => $user->email
                    ]
                ])
                ->log('User profile updated');

            // Login activity
            for ($i = 0; $i < rand(1, 5); $i++) {
                activity()
                    ->causedBy($user)
                    ->event('login')
                    ->withProperties([
                        'ip_address' => '192.168.1.' . rand(1, 255),
                        'user_agent' => 'Mozilla/5.0 (Test Browser)'
                    ])
                    ->log('User logged in');
            }

            // Some system activities
            if ($index % 3 === 0) {
                activity()
                    ->event('system')
                    ->withProperties([
                        'action' => 'backup_created',
                        'size' => rand(1000, 50000) . 'KB'
                    ])
                    ->log('System backup created');
            }
        }

        $this->command->info('Created ' . Activity::count() . ' test activities');
    }
}
