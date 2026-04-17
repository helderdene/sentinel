<?php

namespace App\Http\Middleware;

use App\Enums\IncidentStatus;
use App\Models\City;
use App\Models\Incident;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $user ? array_merge(
                    $user->only('id', 'name', 'email', 'role', 'avatar', 'email_verified_at'),
                    ['can' => [
                        'manage_users' => $user->can('manage-users'),
                        'manage_incident_types' => $user->can('manage-incident-types'),
                        'manage_barangays' => $user->can('manage-barangays'),
                        'create_incidents' => $user->can('create-incidents'),
                        'dispatch_units' => $user->can('dispatch-units'),
                        'respond_incidents' => $user->can('respond-incidents'),
                        'view_analytics' => $user->can('view-analytics'),
                        'view_all_incidents' => $user->can('view-all-incidents'),
                        'manage_system' => $user->can('manage-system'),
                        'triage_incidents' => $user->can('triage-incidents'),
                        'manual_entry' => $user->can('manual-entry'),
                        'submit_dispatch' => $user->can('submit-dispatch'),
                        'override_priority' => $user->can('override-priority'),
                        'recall_incident' => $user->can('recall-incident'),
                        'view_session_log' => $user->can('view-session-log'),
                    ]]
                ) : null,
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            'city' => fn () => City::current()->only([
                'name',
                'province',
                'country',
                'center_latitude',
                'center_longitude',
                'default_zoom',
                'timezone',
                'contact_number',
                'emergency_hotline',
            ]),
            'channelCounts' => function () use ($user) {
                if (! $user || ! in_array($user->role->value, ['operator', 'dispatcher', 'supervisor', 'admin'])) {
                    return null;
                }

                return Incident::query()
                    ->where('status', IncidentStatus::Pending)
                    ->selectRaw('channel, count(*) as count')
                    ->groupBy('channel')
                    ->pluck('count', 'channel');
            },
        ];
    }
}
