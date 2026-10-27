<?php

namespace App\Providers;

use App\Services\Impl\ProductServiceImpl;
use App\Services\Impl\UserServiceImpl;
use App\Services\ProductService;
use App\Services\UserService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(UserService::class, UserServiceImpl::class);
        $this->app->bind(ProductService::class, ProductServiceImpl::class);
    }

    public function boot(): void
    {
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(20)->by('login-ip:'.$request->ip()),
            Limit::perMinute(5)->by('login:'.hash('sha256', strtolower(is_string($request->input('email')) ? $request->input('email') : '').'|'.$request->ip())),
        ]);
        RateLimiter::for('register', fn (Request $request) => Limit::perMinute(3)->by('register:'.$request->ip()));
    }
}
