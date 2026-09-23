<?php

use App\Enums\ProjectPriority;
use App\Enums\TaskStatus;
use App\Enums\TaskType;
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
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            // The foreign key is added after the sprints table exists.
            $table->foreignId('sprint_id')->nullable()->index();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('type', 20)->default(TaskType::Task->value);
            $table->string('priority', 20)->default(ProjectPriority::Medium->value);
            $table->string('status', 20)->default(TaskStatus::Backlog->value);
            $table->unsignedTinyInteger('story_points')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
            $table->timestamp('completed_at')->nullable();

            $table->index('status');
            $table->index('priority');
            $table->index('type');
            $table->index('created_at');
            $table->index('completed_at');
            $table->index(['project_id', 'sprint_id', 'status', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
