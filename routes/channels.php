<?php

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

$dispatchRoles = [UserRole::Operator, UserRole::Dispatcher, UserRole::Supervisor, UserRole::Admin];

Broadcast::channel('dispatch.incidents', function (User $user) use ($dispatchRoles): bool {
    return in_array($user->role, $dispatchRoles);
});

Broadcast::channel('dispatch.units', function (User $user) use ($dispatchRoles): bool {
    return in_array($user->role, $dispatchRoles);
});

Broadcast::channel('fras.cameras', function (User $user) use ($dispatchRoles): bool {
    return in_array($user->role, $dispatchRoles);
});

Broadcast::channel('fras.enrollments', function (User $user): bool {
    return in_array($user->role, [UserRole::Supervisor, UserRole::Admin]);
});

Broadcast::channel('user.{id}', function (User $user, string $id): bool {
    return $user->id === (int) $id;
});

Broadcast::channel('incident.{incidentId}.messages', function (User $user, string $incidentId) use ($dispatchRoles): bool {
    if (in_array($user->role, $dispatchRoles)) {
        return true;
    }

    if ($user->role === UserRole::Responder && $user->unit) {
        return $user->unit->activeIncidents()->where('incidents.id', $incidentId)->exists();
    }

    return false;
});

Broadcast::channel('incident.{incidentId}', function (User $user, string $incidentId) use ($dispatchRoles): bool {
    if (in_array($user->role, $dispatchRoles)) {
        return true;
    }

    if ($user->role === UserRole::Responder && $user->unit) {
        return $user->unit->activeIncidents()->where('incidents.id', $incidentId)->exists();
    }

    return false;
});

Broadcast::channel('dispatch', function (User $user) use ($dispatchRoles): array|false {
    if (! in_array($user->role, $dispatchRoles)) {
        return false;
    }

    return [
        'id' => $user->id,
        'name' => $user->name,
        'role' => $user->role->value,
    ];
});
