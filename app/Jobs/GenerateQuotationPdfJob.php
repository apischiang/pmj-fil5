<?php

namespace App\Jobs;

use App\Filament\Resources\Quotations\Support\QuotationPdfGenerator;
use App\Models\Quotation;
use Filament\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class GenerateQuotationPdfJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public int $quotationId) {}

    public function handle(QuotationPdfGenerator $generator): void
    {
        $quotation = Quotation::query()
            ->with(['creator', 'customer', 'items'])
            ->findOrFail($this->quotationId);

        $relativePath = $quotation->pdf_path ?: $this->resolvePdfPath($quotation);
        $disk = Storage::disk('local');

        $quotation->forceFill([
            'pdf_status' => 'generating',
            'pdf_path' => $relativePath,
            'pdf_error' => null,
            'pdf_failed_at' => null,
        ])->save();

        $disk->makeDirectory(dirname($relativePath));

        if ($disk->exists($relativePath)) {
            $disk->delete($relativePath);
        }

        $generator->save($quotation, $disk->path($relativePath));

        $quotation->forceFill([
            'pdf_status' => 'generated',
            'pdf_path' => $relativePath,
            'pdf_generated_at' => now(),
            'pdf_failed_at' => null,
            'pdf_error' => null,
        ])->save();

        $this->sendGeneratedNotification($quotation);
    }

    public function failed(Throwable $exception): void
    {
        $quotation = Quotation::query()
            ->with('creator')
            ->find($this->quotationId);

        if (! $quotation) {
            return;
        }

        $quotation->forceFill([
            'pdf_status' => 'failed',
            'pdf_failed_at' => now(),
            'pdf_generated_at' => null,
            'pdf_error' => Str::limit($exception->getMessage(), 65535),
        ])->save();

        $this->sendFailedNotification($quotation);
    }

    protected function resolvePdfPath(Quotation $quotation): string
    {
        $filename = Str::of($quotation->quotation_number ?: 'quotation-'.$quotation->getKey())
            ->replace(['/', '\\'], '-')
            ->slug('-')
            ->append('.pdf')
            ->toString();

        return "quotations/pdfs/{$quotation->getKey()}/{$filename}";
    }

    protected function sendGeneratedNotification(Quotation $quotation): void
    {
        if (! $quotation->creator) {
            return;
        }

        Notification::make()
            ->title('PDF quotation selesai dibuat')
            ->body("Quotation {$quotation->quotation_number} sudah siap untuk diunduh.")
            ->success()
            ->sendToDatabase($quotation->creator, isEventDispatched: true);
    }

    protected function sendFailedNotification(Quotation $quotation): void
    {
        if (! $quotation->creator) {
            return;
        }

        $errorMessage = Str::of($quotation->pdf_error ?: 'Terjadi kesalahan saat membuat PDF.')
            ->squish()
            ->limit(180)
            ->toString();

        Notification::make()
            ->title('PDF quotation gagal dibuat')
            ->body("Quotation {$quotation->quotation_number} gagal dibuat. {$errorMessage}")
            ->danger()
            ->sendToDatabase($quotation->creator, isEventDispatched: true);
    }
}
