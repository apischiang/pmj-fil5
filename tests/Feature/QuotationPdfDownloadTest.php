<?php

use App\Models\Customer;
use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('authorized user can download a quotation pdf from signed route', function () {
    Pdf::fake();

    $user = User::factory()->create();
    Permission::findOrCreate('View:Quotation', 'web');
    $user->givePermissionTo('View:Quotation');

    $this->actingAs($user);

    $customer = Customer::create([
        'name' => 'Jane Buyer',
        'company_name' => 'PT Demo Customer',
        'address' => 'Bekasi',
        'email' => 'buyer@example.com',
    ]);

    $quotation = Quotation::create([
        'customer_id' => $customer->getKey(),
        'quotation_number' => 'EST-123',
        'date' => now(),
        'expiry_date' => now()->addWeek(),
        'grand_total' => 100000,
    ]);

    $response = $this->get(
        URL::temporarySignedRoute('quotations.pdf.download', now()->addMinutes(5), ['quotation' => $quotation])
    );

    $response->assertSuccessful();

    Pdf::assertRespondedWithPdf(function ($pdf) use ($quotation): bool {
        return $pdf->viewName === 'pdf.quotation'
            && $pdf->isDownload()
            && $pdf->downloadName === $quotation->quotation_number.'.pdf';
    });
});

test('quotation pdf route rejects invalid signatures', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('View:Quotation', 'web');
    $user->givePermissionTo('View:Quotation');

    $this->actingAs($user);

    $quotation = Quotation::create([
        'quotation_number' => 'EST-999',
    ]);

    $this->get(route('quotations.pdf.download', ['quotation' => $quotation]))
        ->assertForbidden();
});
