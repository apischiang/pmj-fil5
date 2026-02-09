<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Quotation extends Model
{
    protected $fillable = [
        'customer_id',
        'quotation_number',
        'date',
        'expiry_date',
        'status',
        'notes',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'grand_total',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'expiry_date' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($quotation) {
            if (Auth::check()) {
                $quotation->created_by = Auth::id();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuotationItem::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
