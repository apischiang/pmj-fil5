<?php

namespace App\Filament\Resources\Quotations\Support;

use App\Models\Quotation;
use App\Models\QuotationItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\Browsershot\Browsershot;
use Spatie\LaravelPdf\Enums\Format;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Throwable;

class QuotationPdfGenerator
{
    public function download(Quotation $quotation): PdfBuilder
    {
        $quotation->loadMissing(['creator', 'customer', 'items']);

        $items = $quotation->items
            ->sortBy('item_sequence')
            ->values();

        $filename = Str::of($quotation->quotation_number ?: 'quotation')
            ->replace(['/', '\\'], '-')
            ->append('.pdf')
            ->toString();

        return Pdf::view('pdf.quotation', [
            'record' => $quotation,
            'visibleItems' => $items,
            'imageFigures' => $this->buildImageFigures($items),
            'watermarkDataUri' => $this->resolvePublicImageDataUri('images/quotation-watermark.png'),
        ])
            ->format(Format::A4)
            ->margins()
            ->withBrowsershot(function (Browsershot $browsershot): void {
                if ($chromePath = $this->resolveChromePath()) {
                    $browsershot->setChromePath($chromePath);
                }

                $browsershot->newHeadless();
            })
            ->download($filename);
    }

    /**
     * @return Collection<int, array{label:string,item_name:string,description:?string,image_data_uri:string}>
     */
    protected function buildImageFigures(Collection $items): Collection
    {
        return $items
            ->filter(fn (QuotationItem $item): bool => filled($item->image))
            ->values()
            ->map(function (QuotationItem $item, int $index): ?array {
                $imageDataUri = $this->resolveStoredImageDataUri($item->image);

                if ($imageDataUri === null) {
                    return null;
                }

                return [
                    'label' => 'Figure '.($index + 1),
                    'item_name' => $item->item_name ?: '-',
                    'description' => $item->description,
                    'image_data_uri' => $imageDataUri,
                ];
            })
            ->filter()
            ->values();
    }

    protected function resolvePublicImageDataUri(string $relativePath): ?string
    {
        $absolutePath = public_path($relativePath);

        if (! is_file($absolutePath)) {
            return null;
        }

        $contents = @file_get_contents($absolutePath);

        if (! is_string($contents) || $contents === '') {
            return null;
        }

        $mimeType = mime_content_type($absolutePath) ?: 'image/png';

        return 'data:'.$mimeType.';base64,'.base64_encode($contents);
    }

    protected function resolveStoredImageDataUri(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        try {
            $disk = Storage::disk(config('filesystems.default'));

            if (! $disk->exists($path)) {
                return null;
            }

            $contents = $disk->get($path);
            $mimeType = $disk->mimeType($path) ?: 'image/png';

            if (! is_string($contents) || $contents === '') {
                return null;
            }

            return 'data:'.$mimeType.';base64,'.base64_encode($contents);
        } catch (Throwable) {
            return null;
        }
    }

    protected function resolveChromePath(): ?string
    {
        $configuredChromePath = config('laravel-pdf.browsershot.chrome_path');

        if (is_string($configuredChromePath) && $configuredChromePath !== '' && is_file($configuredChromePath)) {
            return $configuredChromePath;
        }

        $homeDirectory = rtrim((string) str_replace('\\', '/', getenv('HOME') ?: ''), '/');

        $candidates = [
            '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
            '/Applications/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing',
            $homeDirectory !== '' ? $homeDirectory.'/.cache/puppeteer/chrome/*/chrome-mac-arm64/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing' : null,
            $homeDirectory !== '' ? $homeDirectory.'/.cache/puppeteer/chrome/*/chrome-mac/Google Chrome for Testing.app/Contents/MacOS/Google Chrome for Testing' : null,
            $homeDirectory !== '' ? $homeDirectory.'/.cache/puppeteer/chrome-headless-shell/*/chrome-headless-shell-mac-arm64/chrome-headless-shell' : null,
            $homeDirectory !== '' ? $homeDirectory.'/.cache/puppeteer/chrome-headless-shell/*/chrome-headless-shell-mac-x64/chrome-headless-shell' : null,
        ];

        foreach (array_filter($candidates) as $candidate) {
            if (str_contains($candidate, '*')) {
                $matches = glob($candidate);

                if ($matches !== false) {
                    rsort($matches);

                    foreach ($matches as $match) {
                        if (is_file($match)) {
                            return $match;
                        }
                    }
                }

                continue;
            }

            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
