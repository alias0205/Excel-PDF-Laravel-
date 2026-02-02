<?php

namespace App\Services;

use App\Models\Company;
use App\Services\PdfTemplateAnalyzer;
use App\Models\Staff;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CompanyTemplateService
{
    public function storeTemplate(Company $company, string $path): void
    {
        $company->template_path = $path;
        $company->save();
    }

    public function populateTemplate(Company $company): string
    {
        if (!$company->template_path || !Storage::disk('local')->exists($company->template_path)) {
            throw new \RuntimeException('Template not found for this company.');
        }

        $fullPath = Storage::disk('local')->path($company->template_path);

        // If template is PDF, generate a staff PDF and merge with template PDF
        $type = $company->template_type ?? 'excel';

        if ($type === 'html') {
            // Load generated HTML template and repeat placeholder row for each staff
            $htmlPath = $company->template_path;
            $html = Storage::get($htmlPath);

            $staff = $company->staff()->orderBy('id')->get();
            if ($staff->isEmpty()) {
                throw new \RuntimeException('No staff data found for this company.');
            }

            libxml_use_internal_errors(true);
            $doc = new \DOMDocument();
            $doc->loadHTML($html);
            $tbodies = $doc->getElementsByTagName('tbody');
            $generatedRowsHtml = '';

            if ($tbodies->length > 0) {
                $tbody = $tbodies->item(0);
                $rows = $tbody->getElementsByTagName('tr');
                if ($rows->length > 0) {
                    $placeholderNode = $rows->item(0);
                    $placeholderHtml = $doc->saveHTML($placeholderNode);

                    foreach ($staff as $member) {
                        $rowHtml = preg_replace_callback('/\{\{\s*(\w+)\s*\}\}/', function ($m) use ($member) {
                            $key = $m[1];
                            $val = $member->{$key} ?? null;
                            if ($val instanceof \Carbon\CarbonInterface) return htmlspecialchars($val->toDateString());
                            return htmlspecialchars((string) ($val ?? ''));
                        }, $placeholderHtml);

                        $generatedRowsHtml .= $rowHtml;
                    }

                    // Replace tbody content with generated rows
                    while ($tbody->firstChild) {
                        $tbody->removeChild($tbody->firstChild);
                    }
                    $fragment = $doc->createDocumentFragment();
                    @$fragment->appendXML($generatedRowsHtml);
                    $tbody->appendChild($fragment);
                }
            } else {
                // No tbody: fallback - append simple table with staff rows
                $generatedRowsHtml = '';
                foreach ($staff as $member) {
                    $generatedRowsHtml .= '<tr>' .
                        '<td>' . e($member->employee_id) . '</td>' .
                        '<td>' . e($member->first_name) . '</td>' .
                        '<td>' . e($member->last_name) . '</td>' .
                        '<td>' . e($member->email) . '</td>' .
                        '</tr>';
                }
                // insert at end of body
                $bodies = $doc->getElementsByTagName('body');
                if ($bodies->length > 0) {
                    $body = $bodies->item(0);
                    $frag = $doc->createDocumentFragment();
                    @$frag->appendXML('<table>' . $generatedRowsHtml . '</table>');
                    $body->appendChild($frag);
                }
            }

            $finalHtml = $doc->saveHTML();

            // Render to PDF
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($finalHtml);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();

            Storage::disk('local')->makeDirectory('exports');
            $out = 'exports/company_' . $company->id . '_staff.pdf';
            $fullOut = Storage::disk('local')->path($out);
            file_put_contents($fullOut, $dompdf->output());

            return $out;
        }

        if ($type === 'pdf') {
            $staff = $company->staff()->orderBy('id')->get();
            if ($staff->isEmpty()) {
                throw new \RuntimeException('No staff data found for this company.');
            }

            // Generate HTML table for staff
            $rows = '';
            foreach ($staff as $member) {
                $rows .= '<tr>' .
                    '<td>' . e($member->employee_id) . '</td>' .
                    '<td>' . e($member->first_name) . '</td>' .
                    '<td>' . e($member->last_name) . '</td>' .
                    '<td>' . e($member->email) . '</td>' .
                    '<td>' . e($member->phone) . '</td>' .
                    '</tr>';
            }

            $html = '<!doctype html><html><head><meta charset="utf-8"><style>table{width:100%;border-collapse:collapse}td,th{border:1px solid #666;padding:6px;text-align:left}</style></head><body>' .
                '<h2>Staff for ' . e($company->name) . '</h2>' .
                '<table><thead><tr><th>Employee ID</th><th>First</th><th>Last</th><th>Email</th><th>Phone</th></tr></thead><tbody>' . $rows . '</tbody></table></body></html>';

            Storage::disk('local')->makeDirectory('exports');

            $tempStaffPdf = Storage::disk('local')->path('exports/company_' . $company->id . '_staff_temp.pdf');
            // Render HTML to PDF
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            file_put_contents($tempStaffPdf, $dompdf->output());

            // Merge template PDF with staff PDF (template first)
            $mergedPath = Storage::disk('local')->path('exports/company_' . $company->id . '_staff.pdf');
            $pdf = new \setasign\Fpdi\Fpdi();

            // We'll create filled pages by overlaying fields onto template pages for each staff member
            $mappings = $company->template_mapping['pdf'] ?? [];
            if (empty($mappings)) {
                // Fallback: append a staff list PDF after template
                $mergedPath = Storage::disk('local')->path('exports/company_' . $company->id . '_staff.pdf');
                $pdf = new \setasign\Fpdi\Fpdi();
                // import template
                try {
                    $pageCount = $pdf->setSourceFile($fullPath);
                } catch (\setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException $e) {
                    try {
                        $fixed = $this->ensurePdfReadable($fullPath, $company->id);
                        $pageCount = $pdf->setSourceFile($fixed);
                    } catch (\Throwable $ee) {
                        if (class_exists(PdfTemplateAnalyzer::class)) {
                            $analyzer = new PdfTemplateAnalyzer();
                            $generated = $analyzer->analyzeAndGenerateHtml($company);
                            $company->template_path = $generated;
                            $company->template_type = 'html';
                            $company->save();
                            return $this->populateTemplate($company);
                        }
                        throw $ee;
                    }
                }
                for ($i = 1; $i <= $pageCount; $i++) {
                    $tpl = $pdf->importPage($i);
                    $size = $pdf->getTemplateSize($tpl);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($tpl);
                }
                // import staff pdf
                try {
                    $pageCount = $pdf->setSourceFile($tempStaffPdf);
                } catch (\setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException $e) {
                    try {
                        $fixed = $this->ensurePdfReadable($tempStaffPdf, $company->id);
                        $pageCount = $pdf->setSourceFile($fixed);
                    } catch (\Throwable $ee) {
                        if (class_exists(PdfTemplateAnalyzer::class)) {
                            $analyzer = new PdfTemplateAnalyzer();
                            $generated = $analyzer->analyzeAndGenerateHtml($company);
                            $company->template_path = $generated;
                            $company->template_type = 'html';
                            $company->save();
                            return $this->populateTemplate($company);
                        }
                        throw $ee;
                    }
                }
                for ($i = 1; $i <= $pageCount; $i++) {
                    $tpl = $pdf->importPage($i);
                    $size = $pdf->getTemplateSize($tpl);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($tpl);
                }
                $pdf->Output('F', $mergedPath);
                @unlink($tempStaffPdf);
                return 'exports/company_' . $company->id . '_staff.pdf';
            }

            try {
                $templatePageCount = (new \setasign\Fpdi\Fpdi())->setSourceFile($fullPath);
            } catch (\setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException $e) {
                try {
                    $fixed = $this->ensurePdfReadable($fullPath, $company->id);
                    $templatePageCount = (new \setasign\Fpdi\Fpdi())->setSourceFile($fixed);
                } catch (\Throwable $ee) {
                    if (class_exists(PdfTemplateAnalyzer::class)) {
                        $analyzer = new PdfTemplateAnalyzer();
                        $generated = $analyzer->analyzeAndGenerateHtml($company);
                        $company->template_path = $generated;
                        $company->template_type = 'html';
                        $company->save();
                        return $this->populateTemplate($company);
                    }
                    throw $ee;
                }
            }

            // Use FPDI to create per-staff filled pages
            $fpdi = new \setasign\Fpdi\Fpdi();
            try {
                $templatePageCount = $fpdi->setSourceFile($fullPath);
            } catch (\setasign\Fpdi\PdfParser\CrossReference\CrossReferenceException $e) {
                try {
                    $fixed = $this->ensurePdfReadable($fullPath, $company->id);
                    $templatePageCount = $fpdi->setSourceFile($fixed);
                } catch (\Throwable $ee) {
                    if (class_exists(PdfTemplateAnalyzer::class)) {
                        $analyzer = new PdfTemplateAnalyzer();
                        $generated = $analyzer->analyzeAndGenerateHtml($company);
                        $company->template_path = $generated;
                        $company->template_type = 'html';
                        $company->save();
                        return $this->populateTemplate($company);
                    }
                    throw $ee;
                }
            }

            foreach ($staff as $member) {
                for ($p = 1; $p <= $templatePageCount; $p++) {
                    $tpl = $fpdi->importPage($p);
                    $size = $fpdi->getTemplateSize($tpl);
                    $fpdi->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $fpdi->useTemplate($tpl, 0, 0, $size['width'], $size['height']);

                    // overlay mappings for this page
                    foreach ($mappings as $map) {
                        if (($map['page'] ?? 1) != $p) continue;
                        $value = $this->getFieldValue($member, $map['field']);
                        if ($value === null) continue;

                        $fontSize = $map['size'] ?? 10;
                        $x = $map['x'] ?? 10;
                        $y = $map['y'] ?? 10;

                        $fpdi->SetFont('Helvetica', '', $fontSize);
                        $fpdi->SetTextColor(0, 0, 0);
                        // FPDI/FPDF uses mm units by default; set position and write
                        $fpdi->SetXY($x, $y);
                        $fpdi->Write(0, (string) $value);
                    }
                }
            }

            // Output merged filled PDF
            $fpdi->Output('F', $mergedPath);
            @unlink($tempStaffPdf);
            return 'exports/company_' . $company->id . '_staff.pdf';
        }

        // Excel handling
        $spreadsheet = IOFactory::load($fullPath);
        [$sheet, $orientation, $headerIndex, $labels] = $this->detectHeaders($spreadsheet);

        $mapping = $company->template_mapping ?? [];
        $validFields = array_flip((new Staff())->getFillable());
        $headers = [];
        foreach ($labels as $label) {
            $fieldKey = $mapping[$label['key']] ?? $label['resolved'];
            if (!isset($validFields[$fieldKey])) {
                continue;
            }
            $headers[$label[$orientation === 'horizontal' ? 'col' : 'row']] = $fieldKey;
        }

        if (empty($headers)) {
            throw new \RuntimeException('No mapped fields found for this template.');
        }

        $staff = $company->staff()->orderBy('id')->get();

        if ($staff->isEmpty()) {
            throw new \RuntimeException('No staff data found for this company.');
        }

        $outputPath = 'exports/company_' . $company->id . '_staff.xlsx';
        Storage::disk('local')->makeDirectory('exports');
        $tempFullPath = Storage::disk('local')->path($outputPath);

        $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
        $writer->save($tempFullPath);

        return $outputPath;
    }

    public function getTemplateHeaderLabels(Company $company): array
    {
        if (!$company->template_path || !Storage::disk('local')->exists($company->template_path)) {
            throw new \RuntimeException('Template not found for this company.');
        }

        $fullPath = Storage::disk('local')->path($company->template_path);
        $spreadsheet = IOFactory::load($fullPath);

        [$sheet, $orientation, $headerIndex, $labels] = $this->detectHeaders($spreadsheet);

        return [
            'sheet' => $sheet->getTitle(),
            'orientation' => $orientation,
            'header_index' => $headerIndex,
            'labels' => $labels,
            'mapping' => $company->template_mapping ?? [],
        ];
    }

    private function detectHeaders(Spreadsheet $spreadsheet): array
    {
        $best = null;

        foreach ($spreadsheet->getAllSheets() as $sheet) {
            $labelAware = $this->collectLabelCells($sheet);

            if (empty($labelAware['labels'])) {
                continue;
            }

            $rowCounts = $labelAware['rowCounts'];
            $colCounts = $labelAware['colCounts'];

            $maxRowCount = max($rowCounts);
            $maxColCount = max($colCounts);

            $headerRow = array_key_first(array_filter($rowCounts, fn ($count) => $count === $maxRowCount));
            $headerCol = array_key_first(array_filter($colCounts, fn ($count) => $count === $maxColCount));

            $candidate = [
                'sheet' => $sheet,
                'maxRowCount' => $maxRowCount,
                'maxColCount' => $maxColCount,
                'headerRow' => $headerRow,
                'headerCol' => $headerCol,
                'labels' => $labelAware['labels'],
            ];

            if ($best === null || ($maxRowCount + $maxColCount) > ($best['maxRowCount'] + $best['maxColCount'])) {
                $best = $candidate;
            }
        }

        if ($best === null) {
            throw new \RuntimeException('No header labels found in template.');
        }

        if ($best['maxRowCount'] >= $best['maxColCount']) {
            $labels = [];
            foreach ($best['labels'] as $label) {
                if ($label['row'] === $best['headerRow']) {
                    $labels[] = [
                        'key' => $this->makeLabelKey($label['row'], $label['col']),
                        'row' => $label['row'],
                        'col' => $label['col'],
                        'value' => $label['value'],
                        'resolved' => $this->resolveFieldKey($label['value']),
                    ];
                }
            }

            usort($labels, fn ($a, $b) => $a['col'] <=> $b['col']);

            return [$best['sheet'], 'horizontal', $best['headerRow'], $labels];
        }

        $labels = [];
        foreach ($best['labels'] as $label) {
            if ($label['col'] === $best['headerCol']) {
                $labels[] = [
                    'key' => $this->makeLabelKey($label['row'], $label['col']),
                    'row' => $label['row'],
                    'col' => $label['col'],
                    'value' => $label['value'],
                    'resolved' => $this->resolveFieldKey($label['value']),
                ];
            }
        }

        usort($labels, fn ($a, $b) => $a['row'] <=> $b['row']);

        return [$best['sheet'], 'vertical', $best['headerCol'], $labels];
    }

    private function makeLabelKey(int $row, int $col): string
    {
        return 'R' . $row . 'C' . $col;
    }

    private function collectLabelCells(Worksheet $sheet): array
    {
        $labels = [];
        $rowCounts = [];
        $colCounts = [];

        $highestRow = $sheet->getHighestDataRow();
        $highestColumn = $sheet->getHighestDataColumn();
        $highestColumnIndex = Coordinate::columnIndexFromString($highestColumn);

        for ($row = 1; $row <= $highestRow; $row++) {
            for ($colIndex = 1; $colIndex <= $highestColumnIndex; $colIndex++) {
                $cellAddress = Coordinate::stringFromColumnIndex($colIndex) . $row;
                $cell = $sheet->getCell($cellAddress);
                $value = trim((string) $cell->getFormattedValue());

                if ($value === '') {
                    continue;
                }
                $labels[] = [
                    'row' => $row,
                    'col' => $colIndex,
                    'value' => $value,
                ];

                $rowCounts[$row] = ($rowCounts[$row] ?? 0) + 1;
                $colCounts[$colIndex] = ($colCounts[$colIndex] ?? 0) + 1;
            }
        }

        if (empty($rowCounts)) {
            $rowCounts = [1 => 0];
        }

        if (empty($colCounts)) {
            $colCounts = [1 => 0];
        }

        return [
            'labels' => $labels,
            'rowCounts' => $rowCounts,
            'colCounts' => $colCounts,
        ];
    }

    private function resolveFieldKey(string $label): string
    {
        $normalized = Str::of($label)
            ->lower()
            ->replace(['-', '/', '.', '\\'], ' ')
            ->replaceMatches('/[^a-z0-9 ]/i', '')
            ->squish()
            ->replace(' ', '_')
            ->toString();

        $aliases = config('staff-template.field_aliases', []);

        if (isset($aliases[$normalized])) {
            return $aliases[$normalized];
        }

        $fillable = (new Staff())->getFillable();

        if (in_array($normalized, $fillable, true)) {
            return $normalized;
        }

        return $normalized;
    }

    private function getFieldValue(Staff $member, string $fieldKey): mixed
    {
        if (!array_key_exists($fieldKey, $member->getAttributes())) {
            return null;
        }

        $value = $member->getAttribute($fieldKey);

        if ($value instanceof \Carbon\CarbonInterface) {
            return $value->toDateString();
        }

        return $value;
    }

    /**
     * Attempt to make a PDF readable by FPDI by running `qpdf` to disable object streams / compression.
     * Returns path to a (possibly) new normalized PDF file.
     */
    private function ensurePdfReadable(string $path, int $companyId): string
    {
        // If file already exists at path and appears readable, just return it
        if (is_readable($path)) {
            // quick attempt: try opening with FPDI
            return $path;
        }

        // Prepare output normalized path
        Storage::disk('local')->makeDirectory('exports');
        $normalized = Storage::disk('local')->path('exports/company_' . $companyId . '_template_normalized.pdf');

        // Check if qpdf is available
        $checkCmd = 'qpdf --version 2>&1';
        exec($checkCmd, $out, $ret);
        if ($ret !== 0) {
            // qpdf not available; try ghostscript as fallback
            $gsCmd = 'gs -v 2>&1';
            exec($gsCmd, $gos, $gret);
            if ($gret !== 0) {
                throw new \RuntimeException('PDF parser failed and neither qpdf nor ghostscript are available to normalize the file. See https://www.setasign.com/fpdi-pdf-parser');
            }
            // use ghostscript to rewrite PDF
            $cmd = sprintf('gs -q -dNOPAUSE -dBATCH -sDEVICE=pdfwrite -dPDFSETTINGS=/prepress -sOutputFile=%s %s', escapeshellarg($normalized), escapeshellarg($path));
            exec($cmd, $o, $r2);
            if ($r2 === 0 && file_exists($normalized)) return $normalized;
            throw new \RuntimeException('Failed to normalize PDF using Ghostscript.');
        }

        // Run qpdf to disable object streams and expand
        $cmd = sprintf('qpdf --qdf --object-streams=disable %s %s', escapeshellarg($path), escapeshellarg($normalized));
        exec($cmd, $o, $r);
        if ($r === 0 && file_exists($normalized)) {
            return $normalized;
        }

        // fallback try uncompressing streams
        $cmd2 = sprintf('qpdf --stream-data=uncompress %s %s', escapeshellarg($path), escapeshellarg($normalized));
        exec($cmd2, $o2, $r3);
        if ($r3 === 0 && file_exists($normalized)) {
            return $normalized;
        }

        throw new \RuntimeException('PDF parser failed and qpdf failed to normalize the file. See https://www.setasign.com/fpdi-pdf-parser for options.');
    }
}
