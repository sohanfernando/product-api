<?php

namespace App\Repositories;

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ProductRepository
{
    public function paginate(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = $user->products();
        $search = trim($filters['search'] ?? '');

        if ($search !== '') {
            $pattern = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($search)).'%';

            $query->where(function (Builder $query) use ($pattern): void {
                $query->whereRaw("LOWER(name) LIKE ? ESCAPE '!'", [$pattern])
                    ->orWhereRaw("LOWER(description) LIKE ? ESCAPE '!'", [$pattern]);
            });
        }

        if (isset($filters['min_price'])) {
            $query->where('price', '>=', $filters['min_price']);
        }

        if (isset($filters['max_price'])) {
            $query->where('price', '<=', $filters['max_price']);
        }

        if (array_key_exists('in_stock', $filters)) {
            $operator = (bool) $filters['in_stock'] ? '>' : '=';

            $query->where('stock', $operator, 0);
        }

        $sort = $filters['sort'] ?? 'id';
        $direction = $filters['direction'] ?? 'desc';

        $query->orderBy($sort, $direction);

        if ($sort !== 'id') {
            $query->orderBy('id', $direction);
        }

        return $query->paginate(15)->withQueryString();
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
