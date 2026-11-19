<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Canonical 8 order statuses permitted in production.
     *
     * @return list<array<string, mixed>>
     */
    private function canonicalStatuses(): array
    {
        return [
            [
                'name' => 'Pedido creado',
                'slug' => 'pedido-creado',
                'color' => '#1572e8',
                'sort_order' => 1,
                'is_final' => false,
                'is_cancelled' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido recibido',
                'slug' => 'pedido-recibido',
                'color' => '#48abf7',
                'sort_order' => 2,
                'is_final' => false,
                'is_cancelled' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido en proceso',
                'slug' => 'pedido-en-proceso',
                'color' => '#6861ce',
                'sort_order' => 3,
                'is_final' => false,
                'is_cancelled' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido despachado',
                'slug' => 'pedido-despachado',
                'color' => '#31ce36',
                'sort_order' => 4,
                'is_final' => false,
                'is_cancelled' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido entregado',
                'slug' => 'pedido-entregado',
                'color' => '#2bb930',
                'sort_order' => 5,
                'is_final' => true,
                'is_cancelled' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido devuelto',
                'slug' => 'pedido-devuelto',
                'color' => '#e83e8c',
                'sort_order' => 6,
                'is_final' => true,
                'is_cancelled' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido rechazado',
                'slug' => 'pedido-rechazado',
                'color' => '#dc3545',
                'sort_order' => 7,
                'is_final' => true,
                'is_cancelled' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido cobrado',
                'slug' => 'pedido-cobrado',
                'color' => '#28a745',
                'sort_order' => 8,
                'is_final' => true,
                'is_cancelled' => false,
                'is_active' => true,
            ],
        ];
    }

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasTable('order_statuses')) {
            return;
        }

        $hasOrders = Schema::hasTable('orders');
        $hasHistories = Schema::hasTable('order_status_histories');

        DB::transaction(function () use ($hasOrders, $hasHistories): void {
            $now = now();
            $canonicalStatuses = $this->canonicalStatuses();
            $allowedSlugs = array_column($canonicalStatuses, 'slug');
            $canonicalBySlug = [];

            // 1. Upsert the 8 canonical statuses and normalize sort_order/flags.
            foreach ($canonicalStatuses as $status) {
                $existing = DB::table('order_statuses')->where('slug', $status['slug'])->first();

                if ($existing) {
                    DB::table('order_statuses')
                        ->where('id', $existing->id)
                        ->update([
                            'name' => $status['name'],
                            'color' => $existing->color ?: $status['color'],
                            'sort_order' => $status['sort_order'],
                            'is_final' => $status['is_final'],
                            'is_cancelled' => $status['is_cancelled'],
                            'is_active' => true,
                            'updated_at' => $now,
                        ]);

                    $canonicalBySlug[$status['slug']] = [
                        'id' => (int) $existing->id,
                        'name' => $status['name'],
                    ];
                } else {
                    $id = DB::table('order_statuses')->insertGetId(array_merge($status, [
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]));

                    $canonicalBySlug[$status['slug']] = [
                        'id' => (int) $id,
                        'name' => $status['name'],
                    ];
                }
            }

            // 2. Identify all surplus/legacy/custom statuses outside the 8 canonical slugs.
            $surplusRows = DB::table('order_statuses')
                ->whereNotIn('slug', $allowedSlugs)
                ->orderBy('id')
                ->get();

            if ($surplusRows->isEmpty()) {
                return;
            }

            $slugAliasMap = [
                'pedido-en-preparacion' => 'pedido-en-proceso',
                'en-preparacion' => 'pedido-en-proceso',
                'en-proceso' => 'pedido-en-proceso',
                'pedido-cancelado' => 'pedido-rechazado',
                'cancelado' => 'pedido-rechazado',
                'rechazado' => 'pedido-rechazado',
                'cobro' => 'pedido-cobrado',
                'cobrado' => 'pedido-cobrado',
                'creado' => 'pedido-creado',
                'pendiente' => 'pedido-creado',
                'recibido' => 'pedido-recibido',
                'despachado' => 'pedido-despachado',
                'entregado' => 'pedido-entregado',
                'devuelto' => 'pedido-devuelto',
            ];

            $surplusMap = [];
            foreach ($surplusRows as $old) {
                $oldSlug = strtolower((string) $old->slug);
                if (isset($slugAliasMap[$oldSlug])) {
                    $targetSlug = $slugAliasMap[$oldSlug];
                } elseif ((bool) $old->is_cancelled) {
                    $targetSlug = 'pedido-rechazado';
                } elseif ((bool) $old->is_final) {
                    $targetSlug = 'pedido-entregado';
                } elseif ((int) $old->sort_order <= 1) {
                    $targetSlug = 'pedido-creado';
                } else {
                    $targetSlug = 'pedido-en-proceso';
                }

                $surplusMap[(int) $old->id] = [
                    'old_name' => (string) $old->name,
                    'old_slug' => (string) $old->slug,
                    'target_id' => $canonicalBySlug[$targetSlug]['id'],
                    'target_name' => $canonicalBySlug[$targetSlug]['name'],
                ];
            }

            $surplusIds = array_keys($surplusMap);

            // 3. Annotate order_status_histories.observation BEFORE changing any FKs.
            if ($hasHistories) {
                $histories = DB::table('order_status_histories')
                    ->where(function ($query) use ($surplusIds): void {
                        $query->whereIn('new_status_id', $surplusIds)
                            ->orWhereIn('previous_status_id', $surplusIds);
                    })
                    ->orderBy('id')
                    ->get();

                foreach ($histories as $history) {
                    $originalObs = (string) ($history->observation ?? '');
                    $updatedObs = $originalObs;

                    $newId = (int) $history->new_status_id;
                    if (isset($surplusMap[$newId]) && ! str_contains($updatedObs, '[Estado original:')) {
                        $info = $surplusMap[$newId];
                        if (trim($updatedObs) === '') {
                            $updatedObs = "Estado histórico original: {$info['old_name']} (unificado a {$info['target_name']}). [Estado original: {$info['old_name']}]";
                        } else {
                            $updatedObs = trim($updatedObs)." [Estado original: {$info['old_name']}]";
                        }
                    }

                    $prevId = $history->previous_status_id !== null ? (int) $history->previous_status_id : null;
                    if ($prevId !== null && isset($surplusMap[$prevId]) && ! str_contains($updatedObs, '[Estado previo original:')) {
                        $prevInfo = $surplusMap[$prevId];
                        $suffix = "[Estado previo original: {$prevInfo['old_name']}]";
                        $updatedObs = trim($updatedObs) === '' ? $suffix : trim($updatedObs).' '.$suffix;
                    }

                    if ($updatedObs !== $originalObs) {
                        DB::table('order_status_histories')
                            ->where('id', $history->id)
                            ->update(['observation' => $updatedObs]);
                    }
                }
            }

            // 4. Remap all foreign keys BEFORE deleting any surplus rows.
            foreach ($surplusMap as $oldId => $info) {
                if ($hasOrders) {
                    DB::table('orders')
                        ->where('order_status_id', $oldId)
                        ->update([
                            'order_status_id' => $info['target_id'],
                            'updated_at' => $now,
                        ]);
                }

                if ($hasHistories) {
                    DB::table('order_status_histories')
                        ->where('previous_status_id', $oldId)
                        ->update(['previous_status_id' => $info['target_id']]);

                    DB::table('order_status_histories')
                        ->where('new_status_id', $oldId)
                        ->update(['new_status_id' => $info['target_id']]);
                }
            }

            // 5. Safely delete all surplus order_statuses rows now that zero FKs point to them.
            DB::table('order_statuses')
                ->whereNotIn('slug', $allowedSlugs)
                ->delete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('order_statuses')) {
            return;
        }

        $now = now();
        $legacyStatuses = [
            [
                'name' => 'Pedido en preparacion',
                'slug' => 'pedido-en-preparacion',
                'color' => '#ffad46',
                'sort_order' => 3,
                'is_final' => false,
                'is_cancelled' => false,
                'is_active' => true,
            ],
            [
                'name' => 'Pedido cancelado',
                'slug' => 'pedido-cancelado',
                'color' => '#f25961',
                'sort_order' => 7,
                'is_final' => true,
                'is_cancelled' => true,
                'is_active' => true,
            ],
            [
                'name' => 'Cobro',
                'slug' => 'cobro',
                'color' => '#17a2b8',
                'sort_order' => 11,
                'is_final' => true,
                'is_cancelled' => false,
                'is_active' => true,
            ],
        ];

        foreach ($legacyStatuses as $status) {
            if (! DB::table('order_statuses')->where('slug', $status['slug'])->exists()) {
                DB::table('order_statuses')->insert(array_merge($status, [
                    'created_at' => $now,
                    'updated_at' => $now,
                ]));
            }
        }
    }
};
