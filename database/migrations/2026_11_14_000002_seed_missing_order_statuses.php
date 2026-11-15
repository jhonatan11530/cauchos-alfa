<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('order_statuses')) {
            return;
        }

        $statuses = [
            [
                'name' => 'Pedido devuelto',
                'slug' => 'pedido-devuelto',
                'color' => '#e83e8c',
                'sort_order' => 8,
                'is_final' => true,
                'is_cancelled' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pedido rechazado',
                'slug' => 'pedido-rechazado',
                'color' => '#dc3545',
                'sort_order' => 9,
                'is_final' => true,
                'is_cancelled' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Pedido cobrado',
                'slug' => 'pedido-cobrado',
                'color' => '#28a745',
                'sort_order' => 10,
                'is_final' => true,
                'is_cancelled' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'Cobro',
                'slug' => 'cobro',
                'color' => '#17a2b8',
                'sort_order' => 11,
                'is_final' => true,
                'is_cancelled' => false,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($statuses as $status) {
            $exists = DB::table('order_statuses')->where('slug', $status['slug'])->exists();
            if (! $exists) {
                DB::table('order_statuses')->insert($status);
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('order_statuses')) {
            DB::table('order_statuses')
                ->whereIn('slug', ['pedido-devuelto', 'pedido-rechazado', 'pedido-cobrado', 'cobro'])
                ->delete();
        }
    }
};
