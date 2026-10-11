<?php

namespace App\Providers;

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

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
        // Permission berformat "modul.aksi" dipetakan ke Gate, sehingga @can('employee.create') berlaku di Blade.
        Gate::before(function (User $user, string $ability) {
            if (str_contains($ability, '.')) {
                return $user->hasPermission($ability) ?: null;
            }

            return null;
        });

        Blade::directive('rupiah', fn ($expression) => "<?php echo 'Rp '.number_format((float) ($expression), 0, ',', '.'); ?>");
    }
}
