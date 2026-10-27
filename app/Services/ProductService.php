<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductService
{
    public function paginate(User $user, string $search = ''): LengthAwarePaginator;

    public function find(User $user, int $id): Product;

    public function create(User $user, array $data): Product;

    public function update(User $user, int $id, array $data): Product;

    public function delete(User $user, int $id): void;
}
