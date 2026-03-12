<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateBarangayRequest;
use App\Models\Barangay;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminBarangayController extends Controller
{
    /**
     * Display a listing of barangays.
     */
    public function index(): Response
    {
        $barangays = Barangay::query()
            ->select(['id', 'name', 'district', 'city', 'population', 'risk_level', 'created_at', 'updated_at'])
            ->orderBy('district')
            ->orderBy('name')
            ->get();

        return Inertia::render('admin/Barangays', [
            'barangays' => $barangays,
        ]);
    }

    /**
     * Show the form for editing a barangay.
     */
    public function edit(Barangay $barangay): Response
    {
        return Inertia::render('admin/BarangayForm', [
            'barangay' => $barangay->only(['id', 'name', 'district', 'city', 'population', 'risk_level']),
        ]);
    }

    /**
     * Update the specified barangay metadata.
     */
    public function update(UpdateBarangayRequest $request, Barangay $barangay): RedirectResponse
    {
        $barangay->update($request->validated());

        return redirect()->route('admin.barangays.index')
            ->with('success', 'Barangay updated successfully.');
    }
}
