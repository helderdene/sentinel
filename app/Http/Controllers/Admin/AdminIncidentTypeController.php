<?php

namespace App\Http\Controllers\Admin;

use App\Enums\IncidentPriority;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIncidentTypeRequest;
use App\Http\Requests\Admin\UpdateIncidentTypeRequest;
use App\Models\ChecklistTemplate;
use App\Models\IncidentCategory;
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
            ->with('incidentCategory')
            ->orderBy('category')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        $categories = IncidentCategory::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'icon']);

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
        $categories = IncidentCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'icon']);

        $checklistTemplates = ChecklistTemplate::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('admin/IncidentTypeForm', [
            'checklistTemplates' => $checklistTemplates,
            'priorities' => IncidentPriority::cases(),
            'categories' => $categories,
        ]);
    }

    /**
     * Store a newly created incident type.
     */
    public function store(StoreIncidentTypeRequest $request): RedirectResponse
    {
        $data = $request->validated();

        // Sync the string category field from the selected category model
        if (isset($data['incident_category_id'])) {
            $category = IncidentCategory::find($data['incident_category_id']);

            if ($category) {
                $data['category'] = $category->name;
            }
        }

        IncidentType::query()->create($data);

        return redirect()->route('admin.incident-types.index')
            ->with('success', 'Incident type created successfully.');
    }

    /**
     * Show the form for editing an incident type.
     */
    public function edit(IncidentType $incidentType): Response
    {
        $categories = IncidentCategory::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'icon']);

        $checklistTemplates = ChecklistTemplate::query()
            ->active()
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);

        return Inertia::render('admin/IncidentTypeForm', [
            'checklistTemplates' => $checklistTemplates,
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
        $data = $request->validated();

        // Sync the string category field from the selected category model
        if (isset($data['incident_category_id'])) {
            $category = IncidentCategory::find($data['incident_category_id']);

            if ($category) {
                $data['category'] = $category->name;
            }
        }

        $incidentType->update($data);

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
