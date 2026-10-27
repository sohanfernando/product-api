<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\UserService;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    public function __construct(private UserService $users) {}

    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        return new UserResource($this->users->update($request->user(), $request->safe()->only(['name', 'email', 'password'])));
    }
}
