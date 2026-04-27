<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreIncidentOutcomeRequest;
use App\Http\Requests\Admin\UpdateIncidentOutcomeRequest;
use App\Models\IncidentCategory;
use App\Models\IncidentOutcome;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminIncidentOutcomeController extends Controller
{
    /**
     * Display a listing of incident outcomes.
     */
    public function index(): Response
    {
        $outcomes = IncidentOutcome::query()
            ->orderBy('is_universal')
            ->orderBy('sort_order')
            ->orderBy('label')
            ->get();

        return Inertia::render('admin/IncidentOutcomes', [
            'outcomes' => $outcomes,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('admin/IncidentOutcomeForm', [
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function store(StoreIncidentOutcomeRequest $request): RedirectResponse
    {
        IncidentOutcome::query()->create($request->validated());

        return redirect()->route('admin.incident-outcomes.index')
            ->with('success', 'Incident outcome created successfully.');
    }

    public function edit(IncidentOutcome $incidentOutcome): Response
    {
        return Inertia::render('admin/IncidentOutcomeForm', [
            'outcome' => $incidentOutcome,
            'categories' => $this->categoryOptions(),
        ]);
    }

    public function update(UpdateIncidentOutcomeRequest $request, IncidentOutcome $incidentOutcome): RedirectResponse
    {
        $incidentOutcome->update($request->validated());

        return redirect()->route('admin.incident-outcomes.index')
            ->with('success', 'Incident outcome updated successfully.');
    }

    /**
     * Soft-disable the outcome. Outcomes with historical incidents are kept
     * in the table so analytics queries against past rows still resolve;
     * destroy just flips is_active off so it disappears from the responder
     * picker.
     */
    public function destroy(IncidentOutcome $incidentOutcome): RedirectResponse
    {
        $incidentOutcome->update(['is_active' => false]);

        return redirect()->route('admin.incident-outcomes.index')
            ->with('success', 'Incident outcome disabled successfully.');
    }

    /**
     * Distinct active incident-type category names, used to populate the
     * `applicable_categories` multi-select on the form.
     *
     * @return list<string>
     */
    private function categoryOptions(): array
    {
        return IncidentCategory::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->pluck('name')
            ->all();
    }
}
