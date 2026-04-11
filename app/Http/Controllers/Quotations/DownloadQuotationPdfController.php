<?php

namespace App\Http\Controllers\Quotations;

use App\Http\Controllers\Controller;
use App\Models\Quotation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class DownloadQuotationPdfController extends Controller
{
    public function __invoke(Request $request, Quotation $quotation): StreamedResponse
    {
        if (! $request->hasValidSignature()) {
            throw new AccessDeniedHttpException;
        }

        if (! $request->user()) {
            throw new AccessDeniedHttpException;
        }

        Gate::authorize('view', $quotation);

        abort_if(blank($quotation->pdf_path) || blank($quotation->pdf_generated_at), 404);
        abort_if(! Storage::disk('local')->exists($quotation->pdf_path), 404);

        $filename = Str::of($quotation->quotation_number ?: 'quotation')
            ->replace(['/', '\\'], '-')
            ->append('.pdf')
            ->toString();

        return Storage::disk('local')->download($quotation->pdf_path, $filename);
    }
}
