<?php

namespace App\Services\Documents;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class PdfDocument
{
    /** @param array<string, mixed> $data */
    public function download(string $template, array $data, string $filename): Response
    {
        return Pdf::loadView('documents.'.$template, ['document' => $data])
            ->setPaper('a4')
            ->setOption(['isRemoteEnabled' => false, 'isPhpEnabled' => false, 'isJavascriptEnabled' => false])
            ->download($filename);
    }
}
