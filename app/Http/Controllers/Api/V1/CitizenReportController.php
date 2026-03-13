<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Barangay;
use App\Models\IncidentType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;

class CitizenReportController extends Controller
{
    /**
     * Return incident types visible in the citizen app.
     * Always includes "Other Emergency" regardless of show_in_public_app flag.
     */
    public function incidentTypes(): JsonResponse
    {
        $types = IncidentType::active()
            ->where(function (Builder $q) {
                $q->where('show_in_public_app', true)
                    ->orWhere('code', 'OTHER_EMERGENCY');
            })
            ->orderBy('sort_order')
            ->get();

        return response()->json(['data' => $types]);
    }

    /**
     * Return barangay id and name list (no geometry).
     */
    public function barangays(): JsonResponse
    {
        $barangays = Barangay::query()
            ->orderBy('name')
            ->select('id', 'name')
            ->get();

        return response()->json(['data' => $barangays]);
    }
}
