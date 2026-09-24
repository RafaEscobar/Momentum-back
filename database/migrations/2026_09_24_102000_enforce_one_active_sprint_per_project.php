<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->unsignedTinyInteger('active_project_slot')
                ->nullable()
                ->storedAs("IF(status = 'active', 1, NULL)");
            $table->unique(
                ['project_id', 'active_project_slot'],
                'sprints_one_active_per_project_unique',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('sprints', function (Blueprint $table) {
            $table->dropUnique('sprints_one_active_per_project_unique');
            $table->dropColumn('active_project_slot');
        });
    }
};
