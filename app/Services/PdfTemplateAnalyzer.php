<?php
namespace App\Services;

use Smalot\PdfParser\Parser;
use App\Models\Company;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;

class PdfTemplateAnalyzer
{
    protected Parser $parser;

    public function __construct()
    {
        $this->parser = new Parser();
    }

    /**
     * Analyze company's uploaded PDF template and produce a simple HTML table-based template.
     * Returns the storage path of the generated HTML file.
     */
    public function analyzeAndGenerateHtml(Company $company): string
    {
        $path = $company->template_path;
        if (!$path || !Storage::exists($path)) {
            throw new \RuntimeException('No PDF template found for company.');
        }

        $abs = Storage::path($path);
        $pdf = $this->parser->parseFile($abs);
        $pages = $pdf->getPages();

        // Use the first page text for header/column inference
        $firstPageText = '';
        if (count($pages) > 0) {
            $firstPageText = $pages[0]->getText();
        } else {
            $firstPageText = $pdf->getText();
        }

        // Split into lines and find a candidate header line.
        $lines = preg_split('/\r?\n/', trim($firstPageText));
        $candidate = null;
        foreach ($lines as $line) {
            $trim = trim($line);
            if ($trim === '') continue;
            // header-like: contains multiple groups separated by 2+ spaces or tabs
            if (preg_match('/\s{2,}|\t/', $trim) || substr_count($trim, ' ') >= 4) {
                $candidate = $trim;
                break;
            }
        }

        // fallback: use the first non-empty line
        if ($candidate === null && count($lines) > 0) {
            foreach ($lines as $line) {
                if (trim($line) !== '') { $candidate = trim($line); break; }
            }
        }

        $columns = [];
        if ($candidate) {
            // try split by 2+ spaces or tab
            if (preg_match('/\t/', $candidate)) {
                $columns = preg_split('/\t+/', $candidate);
            } else {
                $columns = preg_split('/\s{2,}/', $candidate);
                if (count($columns) <= 1) {
                    // fallback split by single spaces but try to group into words per column
                    $words = preg_split('/\s+/', $candidate);
                    // heuristically group into 5 columns if many words
                    $target = min(8, max(2, (int)ceil(count($words)/2)));
                    $per = (int)ceil(count($words)/$target);
                    $columns = array_chunk($words, $per);
                    $columns = array_map(fn($arr) => implode(' ', $arr), $columns);
                }
            }
        }

        // Normalize column labels
        $labels = array_map(function($c){ return trim(preg_replace('/\s+/', ' ', $c)); }, $columns ?: []);
        if (empty($labels)) {
            // last resort: create generic columns
            $labels = ['employee_id','first_name','last_name','email'];
        }

        // Build an HTML template: table header + placeholder row using blade-style tags
        $companyHint = Str::slug($company->name ?: 'company');
        $fileName = "templates/{$companyHint}_generated_template_" . time() . '.html';

        $headerCells = array_map(fn($l) => htmlspecialchars($l), $labels);
        $placeholderCells = array_map(function($l){
            // convert human label to snake-case candidate
            $k = Str::snake(Str::lower($l));
            // keep only a-z0-9_ chars
            $k = preg_replace('/[^a-z0-9_]/', '_', $k);
            return '{{ ' . $k . ' }}';
        }, $labels);

        $html = '<!doctype html><html><head><meta charset="utf-8"><title>Generated Template</title></head><body>';
        $html .= '<table border="1" cellpadding="6" cellspacing="0" style="border-collapse:collapse;width:100%;">';
        $html .= '<thead><tr>';
        foreach ($headerCells as $cell) { $html .= "<th>" . $cell . "</th>"; }
        $html .= '</tr></thead>';
        $html .= '<tbody><tr>';
        foreach ($placeholderCells as $cell) { $html .= "<td>" . $cell . "</td>"; }
        $html .= '</tr></tbody></table>';
        $html .= '</body></html>';

        Storage::put($fileName, $html);

        return $fileName;
    }
}
