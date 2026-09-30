<?php

namespace App\Providers;

use App\Models\Asset;
use App\Models\Inspection;
use App\Models\License;
use App\Models\Person;
use App\Models\Report;
use App\Models\Supply;
use App\Models\User;
use App\Services\DeviceIdentity;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        /*
         * The audit log stores subject_type as a short alias rather than a
         * fully qualified class name, so the log stays readable and survives a
         * namespace change. enforceMorphMap means an unmapped model throws
         * instead of quietly writing a class name into the log.
         */
        Relation::enforceMorphMap([
            'asset' => Asset::class,
            'report' => Report::class,
            'person' => Person::class,
            'supply' => Supply::class,
            'inspection' => Inspection::class,
            'licence' => License::class,
            // For what an admin does to the system rather than to one record:
            // exporting data, blocking a device.
            'user' => User::class,
        ]);

        // cPanel provides free Let's Encrypt; there is no reason to serve
        // anything over plain HTTP in production (spec section 13).
        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }

        $this->defineRateLimits();
    }

    /**
     * The entire school shares one public IP, so limiting a write by IP would
     * block the tenth person to report something on a busy Monday. Writes are
     * limited by device token first, with IP only as a loose backstop
     * (spec section 13).
     */
    private function defineRateLimits(): void
    {
        RateLimiter::for('scan', fn (Request $request) => Limit::perMinute(120)->by($request->ip()));

        RateLimiter::for('report', fn (Request $request) => [
            Limit::perHour(3)
                ->by('device:'.app(DeviceIdentity::class)->token($request))
                ->response($this->tooManyResponse()),
            Limit::perHour(60)
                ->by('ip:'.$request->ip())
                ->response($this->tooManyResponse()),
        ]);

        RateLimiter::for('supply', fn (Request $request) => [
            Limit::perHour(10)
                ->by('device:'.app(DeviceIdentity::class)->token($request))
                ->response($this->tooManyResponse()),
            Limit::perHour(100)
                ->by('ip:'.$request->ip())
                ->response($this->tooManyResponse()),
        ]);

        RateLimiter::for('report-status', fn (Request $request) => Limit::perHour(20)
            ->by('device:'.app(DeviceIdentity::class)->token($request))
            ->response($this->tooManyResponse()));
    }

    /**
     * A plain page, not a 429 JSON error. Whoever hits this limit is far more
     * likely an enthusiastic teacher than an attacker (spec section 13).
     */
    private function tooManyResponse(): callable
    {
        return fn () => response()->view('public.too-many', status: 429);
    }
}
