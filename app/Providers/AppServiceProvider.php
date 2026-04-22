<?php

namespace App\Providers;

use App\Contracts\AnalyticsServiceInterface;
use App\Contracts\BfpSyncServiceInterface;
use App\Contracts\DirectionsServiceInterface;
use App\Contracts\GeocodingServiceInterface;
use App\Contracts\HospitalEhrServiceInterface;
use App\Contracts\NdrrmcReportServiceInterface;
use App\Contracts\PnpBlotterServiceInterface;
use App\Contracts\ProximityServiceInterface;
use App\Contracts\SmsParserServiceInterface;
use App\Contracts\SmsServiceInterface;
use App\Contracts\WeatherServiceInterface;
use App\Enums\UserRole;
use App\Events\AssignmentPushed;
use App\Events\IncidentCreated;
use App\Listeners\SendAssignmentPushNotification;
use App\Listeners\SendP1PushNotification;
use App\Models\Incident;
use App\Models\Personnel;
use App\Models\User;
use App\Observers\PersonnelObserver;
use App\Services\AnalyticsService;
use App\Services\MapboxDirectionsService;
use App\Services\ProximityRankingService;
use App\Services\SmsParserService;
use App\Services\StubBfpSyncService;
use App\Services\StubHospitalEhrService;
use App\Services\StubMapboxDirectionsService;
use App\Services\StubMapboxGeocodingService;
use App\Services\StubNdrrmcReportService;
use App\Services\StubPagasaWeatherService;
use App\Services\StubPnpBlotterService;
use App\Services\StubSemaphoreSmsService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
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
        $this->app->bind(AnalyticsServiceInterface::class, AnalyticsService::class);
        $this->app->bind(GeocodingServiceInterface::class, StubMapboxGeocodingService::class);
        $this->app->bind(ProximityServiceInterface::class, ProximityRankingService::class);
        $this->app->bind(SmsServiceInterface::class, StubSemaphoreSmsService::class);
        $this->app->bind(SmsParserServiceInterface::class, SmsParserService::class);
        $this->app->bind(DirectionsServiceInterface::class, function () {
            $apiKey = (string) config('integrations.mapbox.api_key');
            $endpoint = (string) config('integrations.mapbox.directions.endpoint');

            if ($apiKey !== '' && $endpoint !== '') {
                return new MapboxDirectionsService($endpoint, $apiKey);
            }

            return new StubMapboxDirectionsService;
        });
        $this->app->bind(WeatherServiceInterface::class, StubPagasaWeatherService::class);
        $this->app->bind(HospitalEhrServiceInterface::class, StubHospitalEhrService::class);
        $this->app->bind(NdrrmcReportServiceInterface::class, StubNdrrmcReportService::class);
        $this->app->bind(BfpSyncServiceInterface::class, StubBfpSyncService::class);
        $this->app->bind(PnpBlotterServiceInterface::class, StubPnpBlotterService::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configureGates();
        $this->configureRateLimiters();
        $this->configureEventListeners();
        $this->configureObservers();
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

    /**
     * Configure authorization gates per IRMS spec Section 9 permissions matrix.
     */
    protected function configureGates(): void
    {
        Gate::define('manage-users', fn (User $user): bool => $user->role === UserRole::Admin);

        Gate::define('manage-incident-types', fn (User $user): bool => $user->role === UserRole::Admin);

        Gate::define('manage-barangays', fn (User $user): bool => $user->role === UserRole::Admin);

        Gate::define('create-incidents', fn (User $user): bool => in_array($user->role, [
            UserRole::Operator, UserRole::Dispatcher, UserRole::Supervisor, UserRole::Admin,
        ], true));

        Gate::define('dispatch-units', fn (User $user): bool => in_array($user->role, [
            UserRole::Dispatcher, UserRole::Supervisor, UserRole::Admin,
        ], true));

        Gate::define('respond-incidents', fn (User $user): bool => $user->role === UserRole::Responder);

        Gate::define('view-analytics', fn (User $user): bool => in_array($user->role, [
            UserRole::Supervisor, UserRole::Admin,
        ], true));

        Gate::define('view-all-incidents', fn (User $user): bool => in_array($user->role, [
            UserRole::Dispatcher, UserRole::Supervisor, UserRole::Admin,
        ], true));

        Gate::define('manage-system', fn (User $user): bool => $user->role === UserRole::Admin);

        // Intake layer gates
        Gate::define('triage-incidents', fn (User $user): bool => in_array($user->role, [
            UserRole::Operator, UserRole::Supervisor, UserRole::Admin,
        ], true));

        Gate::define('manual-entry', fn (User $user): bool => in_array($user->role, [
            UserRole::Operator, UserRole::Supervisor, UserRole::Admin,
        ], true));

        Gate::define('submit-dispatch', fn (User $user): bool => in_array($user->role, [
            UserRole::Operator, UserRole::Supervisor, UserRole::Admin,
        ], true));

        Gate::define('override-priority', fn (User $user): bool => in_array($user->role, [
            UserRole::Supervisor, UserRole::Admin,
        ], true));

        Gate::define('recall-incident', fn (User $user): bool => in_array($user->role, [
            UserRole::Supervisor, UserRole::Admin,
        ], true));

        Gate::define('view-session-log', fn (User $user): bool => in_array($user->role, [
            UserRole::Supervisor, UserRole::Admin,
        ], true));

        Gate::define('download-incident-report', function (User $user, Incident $incident): bool {
            if (in_array($user->role, [
                UserRole::Operator,
                UserRole::Dispatcher,
                UserRole::Supervisor,
                UserRole::Admin,
            ], true)) {
                return true;
            }

            if ($user->role === UserRole::Responder) {
                if ($user->unit_id === null) {
                    return false;
                }

                return DB::table('incident_unit')
                    ->where('incident_id', $incident->id)
                    ->where('unit_id', $user->unit_id)
                    ->exists();
            }

            return false;
        });

        // Phase 22 FRAS gates (D-27 / 22-PATTERNS §Wave 1).
        Gate::define('view-fras-alerts', fn (User $user): bool => in_array($user->role, [
            UserRole::Operator, UserRole::Supervisor, UserRole::Admin,
        ], true));

        Gate::define('manage-cameras', fn (User $user): bool => in_array($user->role, [
            UserRole::Supervisor, UserRole::Admin,
        ], true));

        Gate::define('manage-personnel', fn (User $user): bool => in_array($user->role, [
            UserRole::Supervisor, UserRole::Admin,
        ], true));

        Gate::define('trigger-enrollment-retry', fn (User $user): bool => in_array($user->role, [
            UserRole::Supervisor, UserRole::Admin,
        ], true));

        Gate::define('view-recognition-image', fn (User $user): bool => in_array($user->role, [
            UserRole::Operator, UserRole::Supervisor, UserRole::Admin,
        ], true));
    }

    /**
     * Configure rate limiters for citizen API endpoints.
     */
    protected function configureRateLimiters(): void
    {
        RateLimiter::for('citizen-reports', function (Request $request) {
            return Limit::perMinute(5)
                ->by($request->ip())
                ->response(function () {
                    return response()->json([
                        'message' => 'Too many report submissions. Please try again later.',
                    ], 429);
                });
        });

        RateLimiter::for('citizen-reads', function (Request $request) {
            return Limit::perMinute(60)->by($request->ip());
        });
    }

    /**
     * Configure event listeners for push notifications.
     */
    protected function configureEventListeners(): void
    {
        Event::listen(
            AssignmentPushed::class,
            SendAssignmentPushNotification::class,
        );

        Event::listen(
            IncidentCreated::class,
            SendP1PushNotification::class,
        );
    }

    /**
     * Configure Eloquent observers for FRAS camera enrollment bridging.
     */
    protected function configureObservers(): void
    {
        Personnel::observe(PersonnelObserver::class);
    }
}
