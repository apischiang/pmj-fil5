<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class PurchaseOrder extends Model
{
    use HasUuids;

    protected $fillable = [
        'type', // 'in' or 'out'
        'buyer_id',
        'vendor_id',
        'po_number',
        'po_date',
        'purchaser_name',
        'payment_term',
        'currency',
        'tax_rate',
        'tax_amount',
        'grand_total',
        'file_attachment',
        'status',
        'created_by',
    ];

    protected $casts = [
        'po_date' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($purchaseOrder) {
            if (Auth::check()) {
                $purchaseOrder->created_by = Auth::id();
            }
        });
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'buyer_id');
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class, 'vendor_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
