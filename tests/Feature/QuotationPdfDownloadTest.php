<?php

use App\Models\Quotation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

test('authorized user can download a quotation pdf from signed route', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('View:Quotation', 'web');
    $user->givePermissionTo('View:Quotation');

    $this->actingAs($user);
    Storage::fake('local');

    $quotation = Quotation::create([
        'quotation_number' => 'EST-123',
        'pdf_status' => 'generated',
        'pdf_path' => 'quotations/pdfs/1/est-123.pdf',
        'pdf_generated_at' => now(),
    ]);

    Storage::disk('local')->put($quotation->pdf_path, 'pdf-content');

    $response = $this->get(
        URL::temporarySignedRoute('quotations.pdf.download', now()->addMinutes(5), ['quotation' => $quotation])
    );

    $response->assertSuccessful();
    $response->assertDownload('EST-123.pdf');
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

test('quotation pdf route returns not found when generated file is unavailable', function () {
    $user = User::factory()->create();
    Permission::findOrCreate('View:Quotation', 'web');
    $user->givePermissionTo('View:Quotation');

    $this->actingAs($user);

    $quotation = Quotation::create([
        'quotation_number' => 'EST-404',
        'pdf_status' => 'queued',
    ]);

    $this->get(URL::temporarySignedRoute('quotations.pdf.download', now()->addMinutes(5), ['quotation' => $quotation]))
        ->assertNotFound();
});
