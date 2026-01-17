<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('user.{userId}', function ($user, $userId) {
    if ((int) $user->id === (int) $userId) {
        return ['id' => $user->id, 'name' => $user->name];
    }
    return false;
});

