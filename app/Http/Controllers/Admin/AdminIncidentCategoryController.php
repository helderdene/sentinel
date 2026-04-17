<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIncidentCategoryRequest;
use App\Http\Requests\Admin\UpdateIncidentCategoryRequest;
use App\Models\IncidentCategory;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminIncidentCategoryController extends Controller
{
    /**
     * Display a listing of incident categories.
     */
    public function index(): Response
    {
        $categories = IncidentCategory::query()
            ->withCount('incidentTypes')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/IncidentCategories', [
            'categories' => $categories,
        ]);
    }

    /**
     * Show the form for creating a new incident category.
     */
    public function create(): Response
    {
        return Inertia::render('admin/IncidentCategoryForm');
    }

    /**
     * Store a newly created incident category.
     */
    public function store(StoreIncidentCategoryRequest $request): RedirectResponse
    {
        IncidentCategory::query()->create($request->validated());

        return redirect()->route('admin.incident-categories.index')
            ->with('success', 'Incident category created successfully.');
    }

    /**
     * Show the form for editing an incident category.
     */
    public function edit(IncidentCategory $incidentCategory): Response
    {
        return Inertia::render('admin/IncidentCategoryForm', [
            'category' => $incidentCategory,
        ]);
    }

    /**
     * Update the specified incident category.
     */
    public function update(UpdateIncidentCategoryRequest $request, IncidentCategory $incidentCategory): RedirectResponse
    {
        $incidentCategory->update($request->validated());

        return redirect()->route('admin.incident-categories.index')
            ->with('success', 'Incident category updated successfully.');
    }

    /**
     * Soft-disable the specified incident category.
     */
    public function destroy(IncidentCategory $incidentCategory): RedirectResponse
    {
        $incidentCategory->update(['is_active' => false]);

        return redirect()->route('admin.incident-categories.index')
            ->with('success', 'Incident category disabled successfully.');
    }
}
