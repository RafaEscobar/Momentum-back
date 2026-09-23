<?php

use App\Enums\SprintStatus;
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
        Schema::create('sprints', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('goal')->nullable();
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->string('status', 20)->default(SprintStatus::Planned->value);
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            $table->index(['project_id', 'status']);
            $table->unique(['project_id', 'name']);
        });

        Schema::table('tasks', function (Blueprint $table) {
            $table->foreign('sprint_id')->references('id')->on('sprints')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropForeign(['sprint_id']);
        });

        Schema::dropIfExists('sprints');
    }
};
