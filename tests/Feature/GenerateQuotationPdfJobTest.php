<?php

use App\Filament\Resources\Quotations\Support\QuotationPdfGenerator;
use App\Jobs\GenerateQuotationPdfJob;
use App\Models\Quotation;
use App\Models\User;
use Filament\Notifications\DatabaseNotification as FilamentDatabaseNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;

uses(RefreshDatabase::class);

test('generate quotation pdf job saves file and marks quotation as generated', function () {
    Pdf::fake();
    Notification::fake();
    Storage::fake('local');

    $creator = User::factory()->create();

    $quotation = Quotation::create([
        'quotation_number' => 'PMJ/QUO/001',
        'pdf_status' => 'queued',
        'pdf_requested_at' => now(),
        'created_by' => $creator->id,
    ]);

    $job = new GenerateQuotationPdfJob($quotation->getKey());
    $job->handle(app(QuotationPdfGenerator::class));

    $quotation->refresh();

    expect($quotation->pdf_status)->toBe('generated');
    expect($quotation->pdf_generated_at)->not->toBeNull();
    expect($quotation->pdf_path)->toBe('quotations/pdfs/'.$quotation->getKey().'/pmj-quo-001.pdf');

    Pdf::assertSaved(Storage::disk('local')->path($quotation->pdf_path));
    Notification::assertSentTo(
        $creator,
        FilamentDatabaseNotification::class,
        fn (FilamentDatabaseNotification $notification): bool => $notification->data['title'] === 'PDF quotation selesai dibuat'
            && $notification->data['status'] === 'success'
            && str($notification->data['body'])->contains('PMJ/QUO/001'),
    );
});

test('generate quotation pdf job marks quotation as failed when generation fails', function () {
    Notification::fake();

    $creator = User::factory()->create();

    $quotation = Quotation::create([
        'quotation_number' => 'PMJ/QUO/ERR',
        'pdf_status' => 'queued',
        'created_by' => $creator->id,
    ]);

    $job = new GenerateQuotationPdfJob($quotation->getKey());
    $job->failed(new RuntimeException('Chrome is unavailable'));

    $quotation->refresh();

    expect($quotation->pdf_status)->toBe('failed');
    expect($quotation->pdf_failed_at)->not->toBeNull();
    expect($quotation->pdf_error)->toContain('Chrome is unavailable');
    Notification::assertSentTo(
        $creator,
        FilamentDatabaseNotification::class,
        fn (FilamentDatabaseNotification $notification): bool => $notification->data['title'] === 'PDF quotation gagal dibuat'
            && $notification->data['status'] === 'danger'
            && str($notification->data['body'])->contains('PMJ/QUO/ERR')
            && str($notification->data['body'])->contains('Chrome is unavailable'),
    );
});
