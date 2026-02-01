<?php

use App\Http\Controllers\CompanyController;
use App\Http\Controllers\CompanyTemplateMappingController;
use App\Http\Controllers\CompanyTemplateController;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\CompanyStaffController;


Route::get('/', function () {
    return view('welcome');
});

Route::get('/company-templates', function () {
    return view('company-template');
});

Route::get('/companies', [CompanyController::class, 'index'])->name('companies.index');
Route::post('/companies', [CompanyController::class, 'store'])->name('companies.store');

Route::post('/companies/{company}/generate-staff', [CompanyStaffController::class, 'generate'])->name('companies.generate-staff');

Route::get('/companies/{company}/template/mapping', [CompanyTemplateMappingController::class, 'show'])
    ->name('companies.template.mapping');
Route::post('/companies/{company}/template/mapping', [CompanyTemplateMappingController::class, 'store'])
    ->name('companies.template.mapping.store');

Route::post('/companies/{company}/template', [CompanyTemplateController::class, 'upload']);
Route::get('/companies/{company}/template', [CompanyTemplateController::class, 'downloadPopulated']);
