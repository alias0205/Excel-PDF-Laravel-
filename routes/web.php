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
Route::get('/companies/{company}/template/file', [CompanyTemplateMappingController::class, 'file'])
    ->name('companies.template.file');
Route::post('/companies/{company}/template/mapping', [CompanyTemplateMappingController::class, 'store'])
    ->name('companies.template.mapping.store');

Route::post('/companies/{company}/template/analyze', [CompanyTemplateMappingController::class, 'analyze'])
    ->name('companies.template.analyze');
Route::get('/companies/{company}/template/preview', [CompanyTemplateMappingController::class, 'preview'])
    ->name('companies.template.preview');

Route::post('/companies/{company}/template', [CompanyTemplateController::class, 'upload']);
Route::get('/companies/{company}/template', [CompanyTemplateController::class, 'downloadPopulated'])
    ->name('companies.template.download');
