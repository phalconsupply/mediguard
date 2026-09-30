<?php

namespace App\Providers;

use App\Models\IssueCase;
use App\Policies\IssueCasePolicy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Gate::policy(IssueCase::class, IssueCasePolicy::class);

        // Gán hàng loạt phải khai báo rõ; bảng nguồn có hơn 130 cột nên
        // một lần gán nhầm có thể ghi đè dữ liệu gốc.
        Model::preventSilentlyDiscardingAttributes(! app()->isProduction());
        Model::preventAccessingMissingAttributes(! app()->isProduction());
    }
}
