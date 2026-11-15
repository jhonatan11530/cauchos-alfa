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
        Schema::table('orders', function (Blueprint $table) {
            // Fulfillment evidence (R1)
            $table->string('dispatch_signature_path')->nullable()->after('notes');
            $table->string('dispatch_photo_path')->nullable()->after('dispatch_signature_path');
            $table->string('delivery_photo_path')->nullable()->after('dispatch_photo_path');
            $table->string('client_location')->nullable()->after('delivery_photo_path');

            // Payment modality and collection (R2, R3)
            $table->string('payment_term')->nullable()->after('client_location'); // 'contado' | 'credito'
            $table->date('payment_due_date')->nullable()->after('payment_term');
            $table->timestamp('paid_at')->nullable()->after('payment_due_date');
            $table->foreignId('payment_responsible_id')->nullable()->after('paid_at')->constrained('users')->nullOnDelete();
            $table->string('payment_support_path')->nullable()->after('payment_responsible_id');

            // Return and rejection (R4)
            $table->string('return_justification_path')->nullable()->after('payment_support_path');
            $table->text('rejection_reason')->nullable()->after('return_justification_path');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropForeign(['payment_responsible_id']);
            $table->dropColumn([
                'dispatch_signature_path',
                'dispatch_photo_path',
                'delivery_photo_path',
                'client_location',
                'payment_term',
                'payment_due_date',
                'paid_at',
                'payment_responsible_id',
                'payment_support_path',
                'return_justification_path',
                'rejection_reason',
            ]);
        });
    }
};
