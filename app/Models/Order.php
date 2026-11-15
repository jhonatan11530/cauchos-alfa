<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends Model
{
    protected $fillable = [
        'code',
        'client_id',
        'billing_type',
        'created_by',
        'order_status_id',
        'ordered_at',
        'notes',
        'dispatch_signature_path',
        'dispatch_photo_path',
        'delivery_photo_path',
        'delivery_latitude',
        'delivery_longitude',
        'client_location',
        'payment_term',
        'payment_due_date',
        'paid_at',
        'payment_responsible_id',
        'payment_support_path',
        'return_justification_path',
        'rejection_reason',
        // Aliases for compatibility
        'payment_method',
        'payment_modality',
        'due_date',
        'collected_by_id',
        'payment_collected_at',
        'vendedor_id',
        'user_id',
        'latitude',
        'longitude',
    ];

    protected function casts(): array
    {
        return [
            'ordered_at' => 'date',
            'payment_due_date' => 'date',
            'paid_at' => 'datetime',
            'delivery_latitude' => 'float',
            'delivery_longitude' => 'float',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function vendedor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    public function estado(): BelongsTo
    {
        return $this->belongsTo(OrderStatus::class, 'order_status_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function histories(): HasMany
    {
        return $this->hasMany(OrderStatusHistory::class)->latest('changed_at');
    }

    public function paymentResponsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_responsible_id');
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payment_responsible_id');
    }

    public function isCredit(): bool
    {
        return $this->payment_term === 'credito';
    }

    public function isPaid(): bool
    {
        return ! is_null($this->paid_at);
    }

    // --- Backward Compatibility Accessors & Mutators ---

    protected function paymentMethod(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payment_term,
            set: fn ($value) => ['payment_term' => $value],
        );
    }

    protected function paymentModality(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payment_term,
            set: fn ($value) => ['payment_term' => $value],
        );
    }

    protected function dueDate(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payment_due_date,
            set: fn ($value) => ['payment_due_date' => $value ? Carbon::parse($value)->toDateString() : null],
        );
    }

    protected function collectedById(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payment_responsible_id,
            set: fn ($value) => ['payment_responsible_id' => $value],
        );
    }

    protected function collectorId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->payment_responsible_id,
            set: fn ($value) => ['payment_responsible_id' => $value],
        );
    }

    protected function paymentCollectedAt(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->paid_at,
            set: fn ($value) => ['paid_at' => $value],
        );
    }

    protected function collectorName(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->paymentResponsible?->name,
        );
    }

    protected function paymentStatus(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->isPaid() ? 'cobrado' : 'pendiente',
        );
    }

    protected function deliveryLocation(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->client_location,
            set: fn ($value) => ['client_location' => $value],
        );
    }

    protected function cancellationReason(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->rejection_reason,
            set: fn ($value) => ['rejection_reason' => $value],
        );
    }

    protected function vendedorId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_by,
            set: fn ($value) => ['created_by' => $value],
        );
    }

    protected function userId(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->created_by,
            set: fn ($value) => ['created_by' => $value],
        );
    }

    protected function latitude(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->delivery_latitude,
            set: fn ($value) => ['delivery_latitude' => $value !== null ? (float) $value : null],
        );
    }

    protected function longitude(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->delivery_longitude,
            set: fn ($value) => ['delivery_longitude' => $value !== null ? (float) $value : null],
        );
    }
}
