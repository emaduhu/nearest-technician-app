<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('technicians', function (Blueprint $table): void {
            if (! Schema::hasColumn('technicians', 'nida_id_image')) {
                $table->longText('nida_id_image')->nullable()->after('image');
            }
            if (! Schema::hasColumn('technicians', 'face_image')) {
                $table->longText('face_image')->nullable()->after('nida_id_image');
            }
            if (! Schema::hasColumn('technicians', 'registration_review_status')) {
                $table->string('registration_review_status', 32)->default('approved')->index()->after('face_image');
            }
            if (! Schema::hasColumn('technicians', 'registration_review_note')) {
                $table->text('registration_review_note')->nullable()->after('registration_review_status');
            }
            if (! Schema::hasColumn('technicians', 'registration_reviewed_at')) {
                $table->timestamp('registration_reviewed_at')->nullable()->after('registration_review_note');
            }
            if (! Schema::hasColumn('technicians', 'registration_reviewed_by')) {
                $table->foreignId('registration_reviewed_by')->nullable()->after('registration_reviewed_at')->constrained('users')->nullOnDelete();
            }
        });
    }

    public function down(): void
    {
        Schema::table('technicians', function (Blueprint $table): void {
            if (Schema::hasColumn('technicians', 'registration_reviewed_by')) {
                $table->dropConstrainedForeignId('registration_reviewed_by');
            }
            foreach ([
                'registration_reviewed_at',
                'registration_review_note',
                'registration_review_status',
                'face_image',
                'nida_id_image',
            ] as $column) {
                if (Schema::hasColumn('technicians', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
