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
        Schema::table('banners', function (Blueprint $table) {
            if (!Schema::hasColumn('banners', 'deleted_at')) {
                $table->softDeletes();
            }
            if (!Schema::hasColumn('banners', 'section')) {
                $table->string('section')->comment('hero_slider, promotions, banner, top_bar')->after('id');
            }
            if (!Schema::hasColumn('banners', 'title')) {
                $table->string('title')->nullable()->after('id');
            }
            if (!Schema::hasColumn('banners', 'description')) {
                $table->text('description')->nullable()->after('title');
            }
            if (!Schema::hasColumn('banners', 'link')) {
                $table->string('link')->nullable()->after('description');
            }
            if (!Schema::hasColumn('banners', 'is_active')) {
                $table->boolean('is_active')->default(true)->after('section');
            }
            if (!Schema::hasColumn('banners', 'sort_order')) {
                $table->integer('sort_order')->default(0)->after('is_active');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('banners', function (Blueprint $table) {
            // No drop columns to avoid data loss on rollback of a fix
            $table->dropSoftDeletes();
        });
    }
};
