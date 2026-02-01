<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Creates the goal_comments table for storing comments and feedback
     * on goals from employees and managers.
     */
    public function up(): void
    {
        Schema::create('goal_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('goal_id');
            // Note: user_id does NOT have a FK constraint because users table is in platform database
            $table->unsignedBigInteger('user_id');
            $table->text('comment');
            $table->boolean('is_private')->default(false);
            $table->timestamps();
            $table->softDeletes();

            // Foreign keys
            $table->foreign('goal_id', 'goal_comment_goal_fk')
                ->references('id')
                ->on('goals')
                ->onDelete('cascade');

            // Indexes
            $table->index('user_id');
            $table->index(['goal_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('goal_comments');
    }
};
