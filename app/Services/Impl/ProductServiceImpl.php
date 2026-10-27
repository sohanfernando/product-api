<?php

namespace App\Services\Impl;

use App\Models\Product;
use App\Models\User;
use App\Repositories\ProductRepository;
use App\Services\ProductService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductServiceImpl implements ProductService
{
    public function __construct(private ProductRepository $products) {}

    public function paginate(User $user, string $search = ''): LengthAwarePaginator
    {
        return $this->products->paginate($user, $search);
    }

    public function find(User $user, int $id): Product
    {
        return $this->products->find($user, $id);
    }

    public function create(User $user, array $data): Product
    {
        return $this->products->create($user, $data);
    }

    public function update(User $user, int $id, array $data): Product
    {
        return $this->products->update($this->find($user, $id), $data);
    }

    public function delete(User $user, int $id): void
    {
        $this->products->delete($this->find($user, $id));
    }
}
