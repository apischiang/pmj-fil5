<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class Invoice extends Model
{
    use HasUuids;

    protected $fillable = [
        'type', // 'in' or 'out'
        'customer_id',
        'vendor_id',
        'invoice_number',
        'date',
        'due_date',
        'status',
        'file_attachment',
        'payment_status',
        'notes',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'grand_total',
        'created_by',
    ];

    protected $casts = [
        'date' => 'date',
        'due_date' => 'date',
    ];

    protected static function booted()
    {
        static::creating(function ($invoice) {
            if (Auth::check()) {
                $invoice->created_by = Auth::id();
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function vendor(): BelongsTo
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
