<?php

namespace App\Services\Impl;

use App\Models\User;
use App\Repositories\UserRepository;
use App\Services\UserService;

class UserServiceImpl implements UserService
{
    public function __construct(private UserRepository $users) {}

    public function register(array $data): User
    {
        return $this->users->create($data);
    }

    public function update(User $user, array $data): User
    {
        return $this->users->update($user, $data);
    }
}
