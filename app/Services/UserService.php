<?php

namespace App\Services;

use App\Models\User;

interface UserService
{
    public function register(array $data): User;

    public function update(User $user, array $data): User;
}
