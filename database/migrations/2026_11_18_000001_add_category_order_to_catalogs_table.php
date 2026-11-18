<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('catalogs', function (Blueprint $table) {
            $table->json('category_order')->nullable()->after('template');
        });

        DB::table('catalogs')
            ->where(function ($query) {
                $query->whereNull('template')
                    ->orWhereNotIn('template', ['default', 'catalogo-web']);
            })
            ->update(['template' => 'default']);
    }

    public function down(): void
    {
        Schema::table('catalogs', function (Blueprint $table) {
            $table->dropColumn('category_order');
        });
    }
};
