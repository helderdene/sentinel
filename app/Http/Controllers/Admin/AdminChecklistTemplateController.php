<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreChecklistTemplateRequest;
use App\Http\Requests\Admin\UpdateChecklistTemplateRequest;
use App\Models\ChecklistTemplate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AdminChecklistTemplateController extends Controller
{
    /**
     * Display a listing of checklist templates.
     */
    public function index(): Response
    {
        $templates = ChecklistTemplate::query()
            ->withCount('incidentTypes')
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/ChecklistTemplates', [
            'templates' => $templates,
        ]);
    }

    /**
     * Show the form for creating a new template.
     */
    public function create(): Response
    {
        return Inertia::render('admin/ChecklistTemplateForm');
    }

    /**
     * Store a newly created template.
     */
    public function store(StoreChecklistTemplateRequest $request): RedirectResponse
    {
        DB::transaction(function () use ($request): void {
            $data = $request->validated();

            if (! empty($data['is_default'])) {
                ChecklistTemplate::query()->where('is_default', true)->update(['is_default' => false]);
            }

            ChecklistTemplate::query()->create($data);
        });

        return redirect()->route('admin.checklist-templates.index')
            ->with('success', 'Checklist template created successfully.');
    }

    /**
     * Show the form for editing a template.
     */
    public function edit(ChecklistTemplate $checklistTemplate): Response
    {
        return Inertia::render('admin/ChecklistTemplateForm', [
            'template' => $checklistTemplate,
        ]);
    }

    /**
     * Update the specified template.
     */
    public function update(UpdateChecklistTemplateRequest $request, ChecklistTemplate $checklistTemplate): RedirectResponse
    {
        DB::transaction(function () use ($request, $checklistTemplate): void {
            $data = $request->validated();

            if (! empty($data['is_default'])) {
                ChecklistTemplate::query()
                    ->where('is_default', true)
                    ->where('id', '!=', $checklistTemplate->id)
                    ->update(['is_default' => false]);
            }

            $checklistTemplate->update($data);
        });

        return redirect()->route('admin.checklist-templates.index')
            ->with('success', 'Checklist template updated successfully.');
    }

    /**
     * Soft-disable the specified template. Defaults cannot be disabled.
     */
    public function destroy(ChecklistTemplate $checklistTemplate): RedirectResponse
    {
        if ($checklistTemplate->is_default) {
            return redirect()->route('admin.checklist-templates.index')
                ->with('error', 'Cannot disable the fallback template. Mark another as default first.');
        }

        $checklistTemplate->update(['is_active' => false]);

        return redirect()->route('admin.checklist-templates.index')
            ->with('success', 'Checklist template disabled successfully.');
    }
}
