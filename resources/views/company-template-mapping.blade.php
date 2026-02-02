<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Template Mapping</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="max-w-6xl mx-auto px-6 py-10">
        <div class="flex items-center justify-between mb-8">
            <div>
                <p class="text-sm font-semibold text-indigo-600">Template mapping</p>
                <h1 class="text-3xl font-semibold tracking-tight mt-2">Map template labels to staff fields</h1>
                <p class="text-slate-600 mt-2">Company: <span class="font-medium">{{ $company->name }}</span> (ID {{ $company->id }})</p>
            </div>
            <a href="{{ url('/company-templates?company=' . $company->id) }}"
                class="text-sm font-medium text-indigo-600 hover:text-indigo-500">Back to tester</a>
        </div>

        @if (session('status'))
            <div class="mb-6 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-700">
                {{ session('status') }}
            </div>
        @endif

        <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
            <div class="mb-4">
                <p class="text-sm text-slate-600">Detected orientation: <span class="font-medium">{{ ucfirst($templateMeta['orientation']) }}</span></p>
                @php $type = $company->template_type ?? ''; @endphp
                <div class="mt-2 flex items-center gap-2">
                    @if ($type === 'html')
                        <a href="{{ route('companies.template.preview', $company) }}" target="_blank" class="inline-flex items-center rounded bg-sky-600 px-3 py-1 text-white text-sm">Preview generated HTML</a>
                    @endif

                    @if (in_array($type, ['html','pdf','excel']))
                        <a href="{{ route('companies.template.download', $company) }}" id="generate-populated" class="inline-flex items-center rounded bg-emerald-600 px-3 py-1 text-white text-sm">Generate populated file</a>
                    @endif
                </div>
            </div>

            @if (($company->template_type ?? 'excel') === 'pdf')
                <div class="mb-6">
                    <p class="text-sm text-slate-600">Uploaded PDF preview:</p>
                    <div id="pdf-viewer" class="mt-3 border rounded p-2 bg-white"></div>
                </div>

                <form method="POST" action="{{ route('companies.template.mapping.store', $company) }}">
                    @csrf
                    <div class="space-y-3">
                        <div class="flex items-center gap-3">
                            <button type="button" id="add-mapping" class="rounded bg-indigo-600 text-white px-3 py-1 text-sm">Add mapping</button>
                            <button type="button" id="auto-detect" class="rounded bg-slate-100 text-slate-800 px-3 py-1 text-sm">Auto-detect columns</button>
                            <form method="POST" action="{{ route('companies.template.analyze', $company) }}">
                                @csrf
                                <button type="submit" class="rounded bg-amber-600 text-white px-3 py-1 text-sm">Analyze PDF → Generate HTML</button>
                            </form>
                        </div>

                        <div id="mappings-list" class="space-y-2">
                            @php
                                $existing = $templateMeta['mapping']['pdf'] ?? [];
                            @endphp
                            @foreach ($existing as $idx => $m)
                                <div class="p-3 border rounded flex gap-2 items-center">
                                    <input type="text" name="pdf_mappings[{{ $idx }}][label]" value="{{ $m['label'] ?? '' }}" placeholder="Label" class="border px-2 py-1 rounded" />
                                    <input type="number" step="1" name="pdf_mappings[{{ $idx }}][page]" value="{{ $m['page'] ?? 1 }}" class="border px-2 py-1 rounded w-20" />
                                    <input type="number" step="0.1" name="pdf_mappings[{{ $idx }}][x]" value="{{ $m['x'] ?? 10 }}" class="border px-2 py-1 rounded w-24" placeholder="X mm" />
                                    <input type="number" step="0.1" name="pdf_mappings[{{ $idx }}][y]" value="{{ $m['y'] ?? 10 }}" class="border px-2 py-1 rounded w-24" placeholder="Y mm" />
                                    <input type="number" step="1" name="pdf_mappings[{{ $idx }}][size]" value="{{ $m['size'] ?? 10 }}" class="border px-2 py-1 rounded w-20" placeholder="Size" />
                                    <select name="pdf_mappings[{{ $idx }}][field]" class="border px-2 py-1 rounded">
                                        <option value="">Select field</option>
                                        @foreach ($fields as $field)
                                            <option value="{{ $field }}" @selected(($m['field'] ?? '') === $field)>{{ $field }}</option>
                                        @endforeach
                                    </select>
                                    <button type="button" class="remove-mapping text-sm text-rose-600">Remove</button>
                                </div>
                            @endforeach
                        </div>

                        <div class="mt-4">
                            <button type="submit" class="rounded bg-indigo-600 text-white px-4 py-2">Save mapping</button>
                        </div>
                    </div>
                </form>

                <script>
                    // PDF.js setup
                    const pdfUrl = '{{ route('companies.template.file', $company) }}';
                    const pdfViewer = document.getElementById('pdf-viewer');
                    if (pdfUrl) {
                        const script = document.createElement('script');
                        script.src = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.min.js';
                        script.onload = () => {
                            pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://cdnjs.cloudflare.com/ajax/libs/pdf.js/2.16.105/pdf.worker.min.js';
                            const loadingTask = pdfjsLib.getDocument(pdfUrl);
                            loadingTask.promise.then(function(pdf) {
                                for (let pageNum = 1; pageNum <= pdf.numPages; pageNum++) {
                                    pdf.getPage(pageNum).then(function(page) {
                                        const viewport = page.getViewport({ scale: 1.25 });
                                        const canvas = document.createElement('canvas');
                                        canvas.style.display = 'block';
                                        const context = canvas.getContext('2d');
                                        canvas.height = viewport.height;
                                        canvas.width = viewport.width;
                                        pdfViewer.appendChild(canvas);

                                        const renderContext = {
                                            canvasContext: context,
                                            viewport: viewport
                                        };
                                        page.render(renderContext);
                                    });
                                }
                            }).catch(function(err){
                                console.error('PDF load error', err);
                                pdfViewer.innerText = 'Could not load PDF preview.';
                            });
                        };
                        document.body.appendChild(script);
                    }
                    document.getElementById('add-mapping').addEventListener('click', function() {
                        const list = document.getElementById('mappings-list');
                        const idx = list.children.length;
                        const div = document.createElement('div');
                        div.className = 'p-3 border rounded flex gap-2 items-center';
                        div.innerHTML = `
                            <input type="text" name="pdf_mappings[${idx}][label]" placeholder="Label" class="border px-2 py-1 rounded" />
                            <input type="number" step="1" name="pdf_mappings[${idx}][page]" value="1" class="border px-2 py-1 rounded w-20" />
                            <input type="number" step="0.1" name="pdf_mappings[${idx}][x]" value="10" class="border px-2 py-1 rounded w-24" placeholder="X mm" />
                            <input type="number" step="0.1" name="pdf_mappings[${idx}][y]" value="10" class="border px-2 py-1 rounded w-24" placeholder="Y mm" />
                            <input type="number" step="1" name="pdf_mappings[${idx}][size]" value="10" class="border px-2 py-1 rounded w-20" placeholder="Size" />
                            <select name="pdf_mappings[${idx}][field]" class="border px-2 py-1 rounded">
                                <option value="">Select field</option>
                                @foreach ($fields as $field)
                                    <option value="{{ $field }}">{{ $field }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="remove-mapping text-sm text-rose-600">Remove</button>
                        `;
                        list.appendChild(div);
                        div.querySelector('.remove-mapping').addEventListener('click', () => div.remove());
                    });
                    document.querySelectorAll('.remove-mapping').forEach(btn => btn.addEventListener('click', function(){ this.closest('div').remove(); }));

                    // Auto-detect vertical whitespace columns across the first page canvas
                    document.getElementById('auto-detect').addEventListener('click', function() {
                        const viewer = document.getElementById('pdf-viewer');
                        const canvas = viewer.querySelector('canvas');
                        if (!canvas) return alert('No PDF page available to analyze.');

                        const ctx = canvas.getContext('2d');
                        const w = canvas.width;
                        const h = canvas.height;
                        const img = ctx.getImageData(0,0,w,h).data;

                        // For each column, compute non-white pixel ratio
                        const colNonWhite = new Array(w).fill(0);
                        for (let x=0;x<w;x++){
                            let nonwhite=0;
                            for (let y=0;y<h;y+=4){
                                const idx = (y*w + x)*4;
                                const r = img[idx], g = img[idx+1], b = img[idx+2];
                                // consider near-white as white
                                if (!(r>250 && g>250 && b>250)) nonwhite++;
                            }
                            colNonWhite[x]=nonwhite;
                        }

                        // find runs of columns with low nonwhite => whitespace columns
                        const threshold = Math.max(1, Math.floor(h/20));
                        const runs = [];
                        let runStart = null;
                        for (let x=0;x<w;x++){
                            if (colNonWhite[x] < threshold) {
                                if (runStart===null) runStart=x;
                            } else {
                                if (runStart!==null){ runs.push([runStart, x-1]); runStart=null; }
                            }
                        }
                        if (runStart!==null) runs.push([runStart, w-1]);

                        if (runs.length===0) return alert('No column-like whitespace found.');

                        // Map each run to a field in order
                        const defaultFields = ['employee_id','first_name','last_name','email','phone','department','title','hire_date','status'];
                        const list = document.getElementById('mappings-list');
                        let idx = list.children.length;
                        runs.forEach((r,i)=>{
                            const centerX = Math.floor((r[0]+r[1])/2);
                            const centerY = Math.floor(h/2);
                            const mmPerPx = 25.4/96; // approx (assumes 96dpi)
                            const x_mm = (centerX * mmPerPx).toFixed(1);
                            const y_mm = (centerY * mmPerPx).toFixed(1);
                            const field = defaultFields[i] || '';
                            const div = document.createElement('div');
                            div.className = 'p-3 border rounded flex gap-2 items-center';
                            div.innerHTML = `
                                <input type="text" name="pdf_mappings[${idx}][label]" value="${field}" placeholder="Label" class="border px-2 py-1 rounded" />
                                <input type="number" step="1" name="pdf_mappings[${idx}][page]" value="1" class="border px-2 py-1 rounded w-20" />
                                <input type="number" step="0.1" name="pdf_mappings[${idx}][x]" value="${x_mm}" class="border px-2 py-1 rounded w-24" placeholder="X mm" />
                                <input type="number" step="0.1" name="pdf_mappings[${idx}][y]" value="${y_mm}" class="border px-2 py-1 rounded w-24" placeholder="Y mm" />
                                <input type="number" step="1" name="pdf_mappings[${idx}][size]" value="10" class="border px-2 py-1 rounded w-20" placeholder="Size" />
                                <select name="pdf_mappings[${idx}][field]" class="border px-2 py-1 rounded">
                                    <option value="">Select field</option>
                                    @foreach ($fields as $field)
                                        <option value="{{ $field }}">{{ $field }}</option>
                                    @endforeach
                                </select>
                                <button type="button" class="remove-mapping text-sm text-rose-600">Remove</button>
                            `;
                            list.appendChild(div);
                            div.querySelector('.remove-mapping').addEventListener('click', () => div.remove());
                            idx++;
                        });
                        alert('Auto-detect added ' + runs.length + ' mappings. Please adjust coordinates if needed.');
                    });
                </script>
                <script>
                    // Confirmation modal logic for Generate populated file
                    document.addEventListener('DOMContentLoaded', function() {
                        const gen = document.getElementById('generate-populated');
                        const modal = document.getElementById('confirm-modal');
                        const ok = document.getElementById('confirm-ok');
                        const cancel = document.getElementById('confirm-cancel');

                        if (!gen) return;

                        gen.addEventListener('click', function(e) {
                            e.preventDefault();
                            modal.classList.remove('hidden');
                            modal.classList.add('flex');
                        });

                        cancel.addEventListener('click', function() {
                            modal.classList.add('hidden');
                            modal.classList.remove('flex');
                        });

                        ok.addEventListener('click', function() {
                            window.location.href = gen.getAttribute('href');
                        });
                    });
                </script>
            @else
                <form method="POST" action="{{ route('companies.template.mapping.store', $company) }}">
                    @csrf
                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-left text-slate-500">
                                    <th class="px-3 py-2">Label</th>
                                    <th class="px-3 py-2">Position</th>
                                    <th class="px-3 py-2">Mapped field</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-200">
                                @foreach ($templateMeta['labels'] as $label)
                                    <tr>
                                        <td class="px-3 py-2 font-medium text-slate-800">{{ $label['value'] }}</td>
                                        <td class="px-3 py-2 text-slate-500">R{{ $label['row'] }}C{{ $label['col'] }}</td>
                                        <td class="px-3 py-2">
                                            <select name="mapping[{{ $label['key'] }}]"
                                                class="w-full rounded-lg border border-slate-300 px-2 py-2">
                                                <option value="">Skip</option>
                                                @foreach ($fields as $field)
                                                    <option value="{{ $field }}"
                                                        @selected(($templateMeta['mapping'][$label['key']] ?? $label['resolved']) === $field)>
                                                        {{ $field }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-6 flex items-center gap-3">
                        <button type="submit"
                            class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-white font-medium shadow-sm hover:bg-indigo-500">
                            Save mapping
                        </button>
                        <span class="text-sm text-slate-500">Any skipped labels will be ignored.</span>
                    </div>
                </form>
            @endif
        </div>
    </div>
    <!-- Confirmation modal -->
    <div id="confirm-modal" class="fixed inset-0 hidden items-center justify-center bg-black bg-opacity-40">
        <div class="bg-white rounded-lg shadow-lg max-w-md w-full p-6">
            <h3 class="text-lg font-semibold">Generate populated file</h3>
            <p class="text-sm text-slate-600 mt-2">This will generate the populated file for all staff. Do you want to continue?</p>
            <div class="mt-4 flex justify-end gap-2">
                <button id="confirm-cancel" class="rounded bg-slate-200 px-3 py-1">Cancel</button>
                <button id="confirm-ok" class="rounded bg-emerald-600 text-white px-3 py-1">Yes, generate</button>
            </div>
        </div>
    </div>
</body>
</html>
