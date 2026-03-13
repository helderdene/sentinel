<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UnitStatus;
use App\Enums\UnitType;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUnitRequest;
use App\Http\Requests\Admin\UpdateUnitRequest;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminUnitController extends Controller
{
    /**
     * Display a listing of units.
     */
    public function index(): Response
    {
        $units = Unit::query()
            ->withCount('users')
            ->with('users:id,name,unit_id')
            ->orderBy('type')
            ->orderBy('id')
            ->get();

        return Inertia::render('admin/Units', [
            'units' => $units,
            'types' => UnitType::cases(),
            'statuses' => [UnitStatus::Available, UnitStatus::Offline],
            'responders' => User::query()
                ->where('role', UserRole::Responder)
                ->select('id', 'name', 'unit_id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Show the form for creating a new unit.
     */
    public function create(): Response
    {
        return Inertia::render('admin/UnitForm', [
            'types' => UnitType::cases(),
            'statuses' => [UnitStatus::Available, UnitStatus::Offline],
            'responders' => User::query()
                ->where('role', UserRole::Responder)
                ->select('id', 'name', 'unit_id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Store a newly created unit.
     */
    public function store(StoreUnitRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $type = UnitType::from($validated['type']);
        $prefixes = [
            UnitType::Ambulance->value => 'AMB',
            UnitType::Fire->value => 'FIRE',
            UnitType::Rescue->value => 'RESCUE',
            UnitType::Police->value => 'POLICE',
            UnitType::Boat->value => 'BOAT',
        ];
        $prefix = $prefixes[$type->value];

        $maxSequence = Unit::query()
            ->where('type', $type)
            ->selectRaw("MAX(CAST(SUBSTRING(id FROM '[0-9]+$') AS INTEGER)) as max_seq")
            ->value('max_seq');

        $nextNumber = ($maxSequence ?? 0) + 1;
        $paddedNumber = str_pad((string) $nextNumber, 2, '0', STR_PAD_LEFT);
        $unitId = "{$prefix}-{$paddedNumber}";

        $callsign = $validated['callsign'] ?? ucfirst($type->value).' '.$nextNumber;

        $unit = Unit::query()->create([
            'id' => $unitId,
            'callsign' => $callsign,
            'type' => $type,
            'agency' => $validated['agency'],
            'crew_capacity' => $validated['crew_capacity'],
            'status' => UnitStatus::from($validated['status']),
            'shift' => $validated['shift'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if (! empty($validated['crew_ids'])) {
            User::query()
                ->whereIn('id', $validated['crew_ids'])
                ->update(['unit_id' => $unit->id]);
        }

        return redirect()->route('admin.units.index')
            ->with('success', 'Unit created successfully.');
    }

    /**
     * Show the form for editing a unit.
     */
    public function edit(Unit $unit): Response
    {
        $unit->load('users:id,name,unit_id');

        return Inertia::render('admin/UnitForm', [
            'unit' => $unit,
            'types' => UnitType::cases(),
            'statuses' => [UnitStatus::Available, UnitStatus::Offline],
            'responders' => User::query()
                ->where('role', UserRole::Responder)
                ->select('id', 'name', 'unit_id')
                ->orderBy('name')
                ->get(),
        ]);
    }

    /**
     * Update the specified unit.
     */
    public function update(UpdateUnitRequest $request, Unit $unit): RedirectResponse
    {
        $validated = $request->validated();

        $unit->update([
            'callsign' => $validated['callsign'] ?? $unit->callsign,
            'agency' => $validated['agency'],
            'crew_capacity' => $validated['crew_capacity'],
            'status' => UnitStatus::from($validated['status']),
            'shift' => $validated['shift'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        if (array_key_exists('crew_ids', $validated)) {
            $crewIds = $validated['crew_ids'] ?? [];

            // Remove old crew not in the new list
            User::query()
                ->where('unit_id', $unit->id)
                ->whereNotIn('id', $crewIds)
                ->update(['unit_id' => null]);

            // Assign new crew
            if (! empty($crewIds)) {
                User::query()
                    ->whereIn('id', $crewIds)
                    ->update(['unit_id' => $unit->id]);
            }
        }

        return redirect()->route('admin.units.index')
            ->with('success', 'Unit updated successfully.');
    }

    /**
     * Decommission the specified unit.
     */
    public function destroy(Unit $unit): RedirectResponse
    {
        if ($unit->activeIncidents()->count() > 0) {
            return redirect()->route('admin.units.index')
                ->with('error', 'Cannot decommission unit with active incidents.');
        }

        $unit->update(['decommissioned_at' => now()]);

        $unit->users()->update(['unit_id' => null]);

        return redirect()->route('admin.units.index')
            ->with('success', 'Unit decommissioned successfully.');
    }

    /**
     * Recommission a decommissioned unit.
     */
    public function recommission(Unit $unit): RedirectResponse
    {
        $unit->update([
            'decommissioned_at' => null,
            'status' => UnitStatus::Available,
        ]);

        return redirect()->route('admin.units.index')
            ->with('success', 'Unit recommissioned successfully.');
    }
}
