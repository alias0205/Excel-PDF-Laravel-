<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Staff;
use App\Services\CompanyTemplateService;
use Illuminate\Http\Request;

class CompanyTemplateMappingController extends Controller
{
    public function show(Company $company, CompanyTemplateService $service)
    {
        $fields = (new Staff())->getFillable();

        if (($company->template_type ?? 'excel') === 'pdf') {
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

        $templateMeta = $service->getTemplateHeaderLabels($company);

        return view('company-template-mapping', [
            'company' => $company,
            'templateMeta' => $templateMeta,
            'fields' => $fields,
        ]);
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
}
