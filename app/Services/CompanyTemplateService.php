<?php

namespace App\Services;

use App\Models\Company;
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
        if (($company->template_type ?? 'excel') === 'pdf') {
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

            $tempStaffPdf = storage_path('app/exports/company_' . $company->id . '_staff_temp.pdf');
            // Render HTML to PDF
            $dompdf = new \Dompdf\Dompdf();
            $dompdf->loadHtml($html);
            $dompdf->setPaper('A4', 'portrait');
            $dompdf->render();
            file_put_contents($tempStaffPdf, $dompdf->output());

            // Merge template PDF with staff PDF (template first)
            $mergedPath = storage_path('app/exports/company_' . $company->id . '_staff.pdf');
            $pdf = new \setasign\Fpdi\Fpdi();

            // We'll create filled pages by overlaying fields onto template pages for each staff member
            $mappings = $company->template_mapping['pdf'] ?? [];
            if (empty($mappings)) {
                // Fallback: append a staff list PDF after template
                $mergedPath = storage_path('app/exports/company_' . $company->id . '_staff.pdf');
                $pdf = new \setasign\Fpdi\Fpdi();
                // import template
                $pageCount = $pdf->setSourceFile($fullPath);
                for ($i = 1; $i <= $pageCount; $i++) {
                    $tpl = $pdf->importPage($i);
                    $size = $pdf->getTemplateSize($tpl);
                    $pdf->AddPage($size['orientation'], [$size['width'], $size['height']]);
                    $pdf->useTemplate($tpl);
                }
                // import staff pdf
                $pageCount = $pdf->setSourceFile($tempStaffPdf);
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

            $templatePageCount = (new \setasign\Fpdi\Fpdi())->setSourceFile($fullPath);

            // Use FPDI to create per-staff filled pages
            $fpdi = new \setasign\Fpdi\Fpdi();
            $templatePageCount = $fpdi->setSourceFile($fullPath);

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
}
