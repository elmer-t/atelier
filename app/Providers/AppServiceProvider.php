<?php

namespace App\Providers;

use App\Models\Project;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

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
        $this->configureDefaults();
        $this->configureRateLimiters();
    }

    /**
     * Rate limiters that aren't just per-IP.
     */
    protected function configureRateLimiters(): void
    {
        // The unlock form is the only wall around private content and IP-keying alone
        // lets a distributed attacker spend a fresh 10/min budget per address. Cap total
        // guesses per project (by slug) as well, so spreading the attempts across IPs
        // no longer buys extra tries; the per-IP limit still bites a single noisy source.
        // The per-slug cap is only correct once trustProxies() resolves the real client
        // IP behind a proxy — configured in bootstrap/app.php (#43).
        RateLimiter::for('project-unlock', function (Request $request): array {
            $project = $request->route('project');
            $slug = $project instanceof Project ? $project->slug : (string) $project;

            return [
                Limit::perMinute(10)->by('project-unlock:slug:'.$slug),
                Limit::perMinute(10)->by('project-unlock:ip:'.$request->ip()),
            ];
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
