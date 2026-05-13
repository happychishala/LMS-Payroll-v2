<?php

namespace App\Providers;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
use App\Filament\CustomLogOutResponse;
use Filament\Http\Responses\Auth\Contracts\LogoutResponse as LogoutResponseContract;
use Illuminate\Support\Facades\Schema;
use App\Models\Loan;
use App\Observers\LoanObserver;
class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->app->bind(LogoutResponseContract::class, CustomLogOutResponse::class);
        Model::unguard();
        Filament::registerNavigationGroups([
            'Customers',
            'Loan Agreement Forms',
            'Wallets',
            'Loans',
            'Expenses',
            'Repayments',
            'Addons',
        ]);
        Schema::defaultStringLength(125);
        Loan::observe(LoanObserver::class);
    }



}
