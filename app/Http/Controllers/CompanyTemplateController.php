<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Services\CompanyTemplateService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class CompanyTemplateController extends Controller
{
    public function upload(Request $request, Company $company, CompanyTemplateService $service)
    {
        $validated = $request->validate([
            'template' => ['required', 'file'],
            'type' => ['required', 'in:excel,pdf'],
        ]);

        $file = $validated['template'];
        $type = $validated['type'];
        $path = $file->storeAs(
            'templates',
            'company_' . $company->id . '_template.' . $file->getClientOriginalExtension()
        );

        $service->storeTemplate($company, $path);
        $company->template_type = $type;
        $company->save();

        return response()->json([
            'message' => 'Template uploaded successfully.',
            'template_path' => $path,
            'mapping_url' => route('companies.template.mapping', $company),
        ]);
    }

    public function downloadPopulated(Company $company, CompanyTemplateService $service)
    {
        try {
            $outputPath = $service->populateTemplate($company);
            $downloadName = 'company_' . $company->id . '_staff.xlsx';
            return Storage::disk('local')->download($outputPath, $downloadName);
        } catch (\RuntimeException $e) {
            return redirect()
                ->to('/company-templates?company=' . $company->id)
                ->with('error', $e->getMessage());
        }
    }
}
