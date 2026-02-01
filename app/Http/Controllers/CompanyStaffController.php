<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Staff;
use Illuminate\Http\RedirectResponse;

class CompanyStaffController extends Controller
{
    public function generate(Company $company): RedirectResponse
    {
        Staff::factory()->count(12)->create([
            'company_id' => $company->id,
        ]);

        return back()->with('status', '12 staff records generated for company "' . $company->name . '".');
    }
}
