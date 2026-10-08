<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Job postings. Laravel's queue tables were renamed to queue_jobs to free this name.
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            // A company with postings cannot be deleted; its jobs must be removed first.
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('title');
            $table->text('description');
            $table->string('location')->nullable();
            $table->string('employment_type', 30)->nullable();
            $table->unsignedInteger('salary_min')->nullable();
            $table->unsignedInteger('salary_max')->nullable();
            $table->char('currency', 3)->default('AED');
            $table->string('status', 20)->default('active');
            $table->string('application_url')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
