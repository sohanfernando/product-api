<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ProductRepository
{
    public function paginate(User $user): LengthAwarePaginator
    {
        return $user->products()->latest('id')->paginate(15);
    }

    public function find(User $user, int $id): Product
    {
        return $user->products()->findOrFail($id);
    }

    public function create(User $user, array $data): Product
    {
        return $user->products()->create($data)->refresh();
    }

    public function update(Product $product, array $data): Product
    {
        $product->update($data);

        return $product->refresh();
    }

    public function delete(Product $product): void
    {
        $product->delete();
    }
}
