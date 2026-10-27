<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductRepository
{
    public function paginate(User $user, string $search = ''): LengthAwarePaginator
    {
        $query = $user->products();
        $search = trim($search);

        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';

            $query->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(description) LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        return $query->latest('id')->paginate(15)->withQueryString();
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
