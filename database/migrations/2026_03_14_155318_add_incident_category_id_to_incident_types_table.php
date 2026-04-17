<?php

use App\Models\IncidentCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Create categories from existing distinct category strings
        $existingCategories = DB::table('incident_types')
            ->select('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        $iconMap = [
            'Medical' => 'Heart',
            'Fire' => 'Flame',
            'Natural Disaster' => 'CloudLightning',
            'Vehicular' => 'Car',
            'Crime / Security' => 'Shield',
            'Hazmat' => 'Biohazard',
            'Water Rescue' => 'Waves',
            'Public Disturbance' => 'Megaphone',
        ];

        $sortOrder = 0;

        foreach ($existingCategories as $categoryName) {
            IncidentCategory::create([
                'name' => $categoryName,
                'icon' => $iconMap[$categoryName] ?? 'AlertTriangle',
                'is_active' => true,
                'sort_order' => $sortOrder++,
            ]);
        }

        // Add the foreign key column
        Schema::table('incident_types', function (Blueprint $table) {
            $table->foreignId('incident_category_id')
                ->nullable()
                ->after('id')
                ->constrained('incident_categories')
                ->nullOnDelete();
        });

        // Populate from existing category strings
        $categories = IncidentCategory::all();

        foreach ($categories as $category) {
            DB::table('incident_types')
                ->where('category', $category->name)
                ->update(['incident_category_id' => $category->id]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('incident_types', function (Blueprint $table) {
            $table->dropConstrainedForeignId('incident_category_id');
        });
    }
};
