<?php

namespace App\Http\Controllers;

use App\Http\Requests\IndexProductRequest;
use App\Http\Requests\StoreProductRequest;
use App\Http\Requests\UpdateProductRequest;
use App\Http\Resources\ProductResource;
use App\Services\ProductService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProductController extends Controller
{
    public function __construct(private ProductService $products) {}

    public function index(IndexProductRequest $request): AnonymousResourceCollection
    {
        return ProductResource::collection($this->products->paginate($request->user(), $request->validated('search') ?? ''));
    }

    public function store(StoreProductRequest $request): JsonResponse
    {
        return (new ProductResource($this->products->create($request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $product): ProductResource
    {
        return new ProductResource($this->products->find($request->user(), $product));
    }

    public function update(UpdateProductRequest $request, int $product): ProductResource
    {
        return new ProductResource($this->products->update($request->user(), $product, $request->validated()));
    }

    public function destroy(Request $request, int $product): JsonResponse
    {
        $this->products->delete($request->user(), $product);

        return response()->json(['message' => 'Product deleted successfully.']);
    }
}
