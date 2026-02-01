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
        Schema::table('performance_cycle_participants', function (Blueprint $table) {
            $table->string('evaluation_status', 30)->default('not_started')->after('status');
            $table->date('self_evaluation_due_date')->nullable()->after('evaluation_status');
            $table->date('peer_review_due_date')->nullable()->after('self_evaluation_due_date');
            $table->date('manager_review_due_date')->nullable()->after('peer_review_due_date');
            $table->tinyInteger('min_peer_reviewers')->unsigned()->default(3)->after('manager_review_due_date');
            $table->tinyInteger('max_peer_reviewers')->unsigned()->default(5)->after('min_peer_reviewers');

            $table->index('evaluation_status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('performance_cycle_participants', function (Blueprint $table) {
            $table->dropIndex(['evaluation_status']);
            $table->dropColumn([
                'evaluation_status',
                'self_evaluation_due_date',
                'peer_review_due_date',
                'manager_review_due_date',
                'min_peer_reviewers',
                'max_peer_reviewers',
            ]);
        });
    }
};
