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
        'pdf_status',
        'pdf_path',
        'pdf_requested_at',
        'pdf_generated_at',
        'pdf_failed_at',
        'pdf_error',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'expiry_date' => 'date',
            'pdf_requested_at' => 'datetime',
            'pdf_generated_at' => 'datetime',
            'pdf_failed_at' => 'datetime',
        ];
    }

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
