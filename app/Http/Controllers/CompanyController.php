<?php

namespace App\Http\Controllers;

use App\Models\Company;
use Illuminate\Http\Request;

class CompanyController extends Controller
{
    public function index()
    {
        $companies = Company::orderBy('id')->get();

        return view('companies.index', [
            'companies' => $companies,
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $company = Company::create($validated);

        return redirect()->route('companies.index')
            ->with('status', "Company #{$company->id} created.");
    }
}
