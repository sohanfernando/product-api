<?php

namespace App\Services;

use App\Models\Product;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductService
{
    /**
     * Paginate the user's products using validated search, filter, and sorting options.
     *
     * @param  array{
     *     search?: string|null,
     *     min_price?: numeric-string|int|float|null,
     *     max_price?: numeric-string|int|float|null,
     *     in_stock?: bool|0|1|'0'|'1',
     *     sort?: 'name'|'price'|'created_at',
     *     direction?: 'asc'|'desc'
     * }  $filters
     */
    public function paginate(User $user, array $filters = []): LengthAwarePaginator;

    public function find(User $user, int $id): Product;

    public function create(User $user, array $data): Product;

    public function update(User $user, int $id, array $data): Product;

    public function delete(User $user, int $id): void;
}
