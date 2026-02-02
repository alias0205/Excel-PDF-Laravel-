<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Staff;
use App\Services\CompanyTemplateService;
use App\Services\PdfTemplateAnalyzer;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Request;

class CompanyTemplateMappingController extends Controller
{
    public function show(Company $company, CompanyTemplateService $service)
    {
        $fields = (new Staff())->getFillable();

        $type = $company->template_type ?? 'excel';

        if ($type === 'pdf') {
            $templateMeta = [
                'sheet' => null,
                'orientation' => 'pdf',
                'header_index' => null,
                'labels' => [],
                'mapping' => $company->template_mapping ?? [],
            ];

            return view('company-template-mapping', [
                'company' => $company,
                'templateMeta' => $templateMeta,
                'fields' => $fields,
            ]);
        }

        if ($type === 'html') {
            $labels = [];
            if ($company->template_path && Storage::exists($company->template_path)) {
                $html = Storage::get($company->template_path);
                libxml_use_internal_errors(true);
                $doc = new \DOMDocument();
                $doc->loadHTML($html);
                $ths = $doc->getElementsByTagName('th');
                if ($ths->length > 0) {
                    foreach ($ths as $i => $th) $labels[] = trim($th->textContent);
                } else {
                    // try first row cells
                    $tables = $doc->getElementsByTagName('table');
                    if ($tables->length > 0) {
                        $first = $tables->item(0);
                        $trs = $first->getElementsByTagName('tr');
                        if ($trs->length > 0) {
                            $cells = $trs->item(0)->getElementsByTagName('td');
                            foreach ($cells as $i => $c) $labels[] = trim($c->textContent);
                        }
                    }
                }
            }

            // Build structured labels array compatible with the view
            $structured = [];
            foreach ($labels as $i => $lbl) {
                $key = 'h' . ($i + 1);
                $structured[] = [
                    'value' => $lbl,
                    'row' => 1,
                    'col' => $i + 1,
                    'key' => $key,
                    'resolved' => null,
                ];
            }

            $templateMeta = [
                'sheet' => null,
                'orientation' => 'horizontal',
                'header_index' => 0,
                'labels' => $structured,
                'mapping' => $company->template_mapping ?? [],
            ];

            return view('company-template-mapping', [
                'company' => $company,
                'templateMeta' => $templateMeta,
                'fields' => $fields,
            ]);
        }

        $templateMeta = $service->getTemplateHeaderLabels($company);

        return view('company-template-mapping', [
            'company' => $company,
            'templateMeta' => $templateMeta,
            'fields' => $fields,
        ]);
    }

    public function file(Company $company)
    {
        if (!$company->template_path || !Storage::disk('local')->exists($company->template_path)) {
            abort(404);
        }

        $fullPath = Storage::disk('local')->path($company->template_path);

        return response()->file($fullPath, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="' . basename($fullPath) . '"',
        ]);
    }

    public function analyze(Company $company, PdfTemplateAnalyzer $analyzer)
    {
        try {
            $generated = $analyzer->analyzeAndGenerateHtml($company);
        } catch (\Throwable $e) {
            return redirect()->route('companies.template.mapping', $company)->with('error', 'Analysis failed: ' . $e->getMessage());
        }

        $company->template_path = $generated;
        $company->template_type = 'html';
        $company->save();

        // Try to auto-suggest mappings: map labels to known staff fields using aliases
        $fields = (new Staff())->getFillable();
        $aliases = config('staff-template.field_aliases', []);
        $html = Storage::get($generated);
        libxml_use_internal_errors(true);
        $doc = new \DOMDocument();
        $doc->loadHTML($html);
        $ths = $doc->getElementsByTagName('th');
        $labels = [];
        if ($ths->length > 0) {
            foreach ($ths as $th) $labels[] = trim($th->textContent);
        } else {
            $tables = $doc->getElementsByTagName('table');
            if ($tables->length > 0) {
                $first = $tables->item(0);
                $trs = $first->getElementsByTagName('tr');
                if ($trs->length > 0) {
                    $cells = $trs->item(0)->getElementsByTagName('td');
                    foreach ($cells as $c) $labels[] = trim($c->textContent);
                }
            }
        }

        $suggested = [];
        foreach ($labels as $i => $lbl) {
            $norm = Str::snake(Str::lower($lbl));
            $norm = preg_replace('/[^a-z0-9_]/', '_', $norm);
            $match = null;
            // check aliases
            foreach ($aliases as $canonical => $variants) {
                if (is_array($variants)) {
                    foreach ($variants as $v) {
                        if (Str::lower($v) === Str::lower($lbl) || Str::snake(Str::lower($v)) === $norm) {
                            $match = $canonical; break 2;
                        }
                    }
                } else {
                    if (Str::lower($variants) === Str::lower($lbl) || Str::snake(Str::lower($variants)) === $norm) {
                        $match = $canonical; break;
                    }
                }
            }

            if (!$match && in_array($norm, $fields)) {
                $match = $norm;
            }

            if ($match) {
                $suggested['excel']['h' . ($i + 1)] = $match;
            }
        }

        $stored = $company->template_mapping ?? [];
        $stored = array_merge($stored, $suggested);
        $company->template_mapping = $stored;
        $company->save();

        return redirect()->route('companies.template.mapping', $company)->with('status', 'HTML template generated from PDF. Suggested mappings added. Adjust as needed.');
    }

    public function store(Request $request, Company $company)
    {
        $pdfMappings = $request->input('pdf_mappings');
        $mapping = $request->input('mapping', []);

        $normalized = [];
        foreach ($mapping as $key => $field) {
            if ($field === null || $field === '') {
                continue;
            }

            $normalized[$key] = $field;
        }

        $stored = $company->template_mapping ?? [];
        $stored['excel'] = $normalized;

        if (!empty($pdfMappings)) {
            $pdf = [];
            foreach ($pdfMappings as $m) {
                if (empty($m['field'])) continue;
                $pdf[] = [
                    'label' => $m['label'] ?? null,
                    'page' => (int) ($m['page'] ?? 1),
                    'x' => (float) ($m['x'] ?? 10),
                    'y' => (float) ($m['y'] ?? 10),
                    'size' => (int) ($m['size'] ?? 10),
                    'field' => $m['field'],
                ];
            }

            $stored['pdf'] = $pdf;
        }

        $company->template_mapping = $stored;
        $company->save();

        return redirect()
            ->route('companies.template.mapping', $company)
            ->with('status', 'Template mapping saved.');
    }

    public function preview(Company $company)
    {
        if (!$company->template_path || !Storage::exists($company->template_path)) {
            abort(404);
        }

        $content = Storage::get($company->template_path);

        return response($content, 200, [
            'Content-Type' => 'text/html; charset=utf-8',
        ]);
    }
}
