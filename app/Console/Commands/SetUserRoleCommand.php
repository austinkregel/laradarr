<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SetUserRoleCommand extends Command
{
    protected $signature = 'user:role {user_id} {role}';

    protected $description = 'Set a user role (admin/default) by user id';

    public function handle(): int
    {
        $userId = (int) $this->argument('user_id');
        $role = strtolower((string) $this->argument('role'));

        if (!in_array($role, ['admin', 'default'], true)) {
            $this->error("Invalid role '{$role}'. Allowed roles: admin, default.");
            return self::FAILURE;
        }

        /** @var User|null $user */
        $user = User::query()->find($userId);
        if (!$user) {
            $this->error("User id={$userId} not found.");
            return self::FAILURE;
        }

        $user->role = $role;
        $user->save();

        $this->info("Updated user id={$user->id} to role={$user->role}.");

        return self::SUCCESS;
    }
}

