<?php

namespace App\Providers;

use App\Models\Bank;
use App\Models\CustomsDeclaration;
use App\Models\ImportRequest;
use App\Models\Merchant;
use App\Models\RequestDocument;
use App\Models\Trader;
use App\Models\User;
use App\Policies\BankPolicy;
use App\Policies\CustomsDeclarationPolicy;
use App\Policies\ImportRequestPolicy;
use App\Policies\MerchantPolicy;
use App\Policies\RequestDocumentPolicy;
use App\Policies\TraderPolicy;
use App\Policies\UserPolicy;
use App\Services\Authorization\PermissionService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Bank::class, BankPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(ImportRequest::class, ImportRequestPolicy::class);
        Gate::policy(Merchant::class, MerchantPolicy::class);
        Gate::policy(RequestDocument::class, RequestDocumentPolicy::class);
        Gate::policy(CustomsDeclaration::class, CustomsDeclarationPolicy::class);
        Gate::policy(Trader::class, TraderPolicy::class);

        Gate::before(function ($user, string $ability) {
            if (! str_contains($ability, '.')) {
                return null;
            }

            return app(PermissionService::class)->userCan($user, $ability);
        });
    }
}
