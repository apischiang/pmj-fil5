<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

class Vendor extends Model
{
    protected $fillable = [
        'name',
        'company_name',
        'initial',
        'address',
        'vat_number',
        'is_pkp',
        'npwp_file',
        'email',
        'phone',
        'created_by',
    ];

    protected $casts = [
        'is_pkp' => 'boolean',
    ];

    protected static function booted()
    {
        static::creating(function ($vendor) {
            if (Auth::check()) {
                $vendor->created_by = Auth::id();
            }
        });
    }

    public function purchaseOrders(): HasMany
    {
        return $this->hasMany(PurchaseOrderOut::class);
    }

    public function invoiceOuts(): HasMany
    {
        return $this->hasMany(InvoiceOut::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
