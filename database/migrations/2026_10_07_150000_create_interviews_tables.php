<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Practice history survives if the job or CV is later removed.
            $table->foreignId('job_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('cv_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 20);
            $table->string('status', 20)->default('pending');
            $table->unsignedTinyInteger('total_score')->nullable();
            // Final AI evaluation (dimension scores, strengths, areas to improve, recommendation).
            $table->json('feedback_json')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('interview_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained()->cascadeOnDelete();
            $table->text('question');
            $table->string('category', 20);
            $table->unsignedTinyInteger('order');
            $table->timestamps();

            $table->unique(['interview_id', 'order']);
        });

        Schema::create('interview_answers', function (Blueprint $table) {
            $table->id();
            // One answer per question.
            $table->foreignId('interview_question_id')->unique()->constrained()->cascadeOnDelete();
            $table->text('answer');
            $table->unsignedTinyInteger('score')->nullable();
            $table->text('feedback')->nullable();
            $table->json('strengths_json')->nullable();
            $table->json('weaknesses_json')->nullable();
            $table->text('improved_answer')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_answers');
        Schema::dropIfExists('interview_questions');
        Schema::dropIfExists('interviews');
    }
};
