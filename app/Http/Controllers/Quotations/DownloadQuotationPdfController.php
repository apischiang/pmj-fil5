<?php

namespace App\Http\Controllers\Quotations;

use App\Filament\Resources\Quotations\Support\QuotationPdfGenerator;
use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\LaravelPdf\PdfBuilder;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DownloadQuotationPdfController extends Controller
{
    public function __invoke(Request $request, Quotation $quotation): PdfBuilder
    {
        if (! $request->hasValidSignature()) {
            throw new AccessDeniedHttpException;
        }

        if (! $request->user()) {
            throw new AccessDeniedHttpException;
        }

        Gate::authorize('view', $quotation);

        $quotation->loadMissing(['creator', 'customer', 'items']);

        return (new QuotationPdfGenerator)->download($quotation);
    }
}
