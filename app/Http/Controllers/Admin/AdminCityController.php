<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateCityRequest;
use App\Models\City;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AdminCityController extends Controller
{
    public function edit(): Response
    {
        return Inertia::render('admin/CitySettings', [
            'city' => City::current()->only([
                'id',
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
        ]);
    }

    public function update(UpdateCityRequest $request): RedirectResponse
    {
        City::current()->update($request->validated());

        return redirect()->route('admin.city.edit')
            ->with('success', 'City settings updated successfully.');
    }
}
