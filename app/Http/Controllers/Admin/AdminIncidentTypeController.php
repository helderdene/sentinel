<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IncidentPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIncidentTypeRequest;
use App\Http\Requests\Admin\UpdateIncidentTypeRequest;
use App\Models\IncidentType;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminIncidentTypeController extends Controller
{
    /**
     * Display a listing of incident types grouped by category.
     */
    public function index(): Response
    {
        $types = IncidentType::query()
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = IncidentType::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('admin/IncidentTypes', [
            'types' => $types,
            'categories' => $categories,
        ]);
    }

    /**
     * Show the form for creating a new incident type.
     */
    public function create(): Response
    {
        $categories = IncidentType::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('admin/IncidentTypeForm', [
            'priorities' => IncidentPriority::cases(),
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created incident type.
     */
    public function store(StoreIncidentTypeRequest $request): RedirectResponse
    {
        IncidentType::query()->create($request->validated());

        return redirect()->route('admin.incident-types.index')
            ->with('success', 'Incident type created successfully.');
    }

    /**
     * Show the form for editing an incident type.
     */
    public function edit(IncidentType $incidentType): Response
    {
        $categories = IncidentType::query()
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return Inertia::render('admin/IncidentTypeForm', [
            'type' => $incidentType,
            'priorities' => IncidentPriority::cases(),
            'categories' => $categories,
        ]);
    }

    /**
     * Update the specified incident type.
     */
    public function update(UpdateIncidentTypeRequest $request, IncidentType $incidentType): RedirectResponse
    {
        $incidentType->update($request->validated());

        return redirect()->route('admin.incident-types.index')
            ->with('success', 'Incident type updated successfully.');
    }

    /**
     * Soft-disable the specified incident type instead of deleting.
     */
    public function destroy(IncidentType $incidentType): RedirectResponse
    {
        $incidentType->update(['is_active' => false]);

        return redirect()->route('admin.incident-types.index')
            ->with('success', 'Incident type disabled successfully.');
    }
}
