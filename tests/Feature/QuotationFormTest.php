<?php

use App\Filament\Resources\Quotations\Pages\CreateQuotation;
use App\Filament\Resources\Quotations\Pages\EditQuotation;
use App\Models\Customer;
use App\Models\Quotation;
use App\Models\QuotationItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('quotation line item total excludes vat while summary still includes it', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('Create:Quotation', 'web');
    Permission::findOrCreate('ViewAny:Quotation', 'web');
    Permission::findOrCreate('View:Quotation', 'web');
    Permission::findOrCreate('Update:Quotation', 'web');
    $user->givePermissionTo([
        'Create:Quotation',
        'ViewAny:Quotation',
        'View:Quotation',
        'Update:Quotation',
    ]);

    $this->actingAs($user);

    $customer = Customer::create([
        'name' => 'Procurement',
        'company_name' => 'PT Contoh Customer',
        'initial' => 'CC',
        'email' => 'procurement@example.com',
    ]);

    Livewire::test(CreateQuotation::class)
        ->fillForm([
            'customer_id' => $customer->getKey(),
            'quotation_number' => 'PMJ/CC/26/04/001',
            'status' => 'draft',
            'date' => now()->toDateString(),
            'expiry_date' => now()->addWeek()->toDateString(),
            'has_vat' => true,
            'items' => [
                [
                    'item_name' => 'Produk A',
                    'description' => 'Test item',
                    'quantity' => 2,
                    'unit_price' => 100000,
                    'discount' => 10,
                    'vat_rate' => 11,
                    'amount' => 180000,
                ],
            ],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $quotation = Quotation::query()
        ->with('items')
        ->where('quotation_number', 'PMJ/CC/26/04/001')
        ->firstOrFail();

    expect((float) $quotation->subtotal)->toBe(200000.0);
    expect((float) $quotation->discount_amount)->toBe(20000.0);
    expect((float) $quotation->tax_amount)->toBe(19800.0);
    expect((float) $quotation->grand_total)->toBe(199800.0);
    expect($quotation->salesperson_id)->toBe($user->getKey());

    $quotationItem = $quotation->items->sole();

    expect((float) $quotationItem->amount)->toBe(180000.0);
    expect((float) $quotationItem->vat_rate)->toBe(11.0);

    expect(QuotationItem::query()->count())->toBe(1);
});

test('salesperson cannot be changed from quotation edit form', function () {
    $user = User::factory()->create();
    $originalSalesperson = User::factory()->create();
    $newSalesperson = User::factory()->create();
    $customer = Customer::create([
        'name' => 'Buyer Edit',
        'company_name' => 'PT Edit Customer',
        'initial' => 'EC',
        'email' => 'buyer-edit@example.com',
    ]);
    Permission::findOrCreate('ViewAny:Quotation', 'web');
    Permission::findOrCreate('View:Quotation', 'web');
    Permission::findOrCreate('Update:Quotation', 'web');
    $user->givePermissionTo([
        'ViewAny:Quotation',
        'View:Quotation',
        'Update:Quotation',
    ]);

    $this->actingAs($user);

    $quotation = Quotation::create([
        'customer_id' => $customer->getKey(),
        'quotation_number' => 'PMJ/CC/26/04/002',
        'status' => 'draft',
        'date' => now()->toDateString(),
        'salesperson_id' => $originalSalesperson->getKey(),
    ]);

    Livewire::test(EditQuotation::class, ['record' => $quotation->getRouteKey()])
        ->fillForm([
            'salesperson_id' => $newSalesperson->getKey(),
        ])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($quotation->fresh()->salesperson_id)->toBe($originalSalesperson->getKey());
});
