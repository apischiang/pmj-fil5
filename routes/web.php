<?php

use App\Http\Controllers\Quotations\DownloadQuotationPdfController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/quotations/{quotation}/pdf', DownloadQuotationPdfController::class)
    ->name('quotations.pdf.download');
