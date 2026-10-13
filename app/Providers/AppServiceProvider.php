<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Database\Eloquent\Factories\Factory;
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
        // Per AGENTS.md section 5 (module layout) and to keep factory
        // files flat in database/factories/, override Laravel default
        // namespace-mirroring factory guesser. Models in App\Models\*
        // map to Database\Factories\{ShortName}Factory (the stock
        // behavior), and models in App\Modules\*\Infrastructure\Models\
        // {Name} also map to Database\Factories\{Name}Factory. Override
        // per-model via the model newFactory() when a different layout
        // is needed.
        Factory::guessFactoryNamesUsing(
            static fn (string $modelName): string => 'Database\\Factories\\'.class_basename($modelName).'Factory',
        );
    }
}
