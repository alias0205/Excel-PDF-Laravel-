<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Company Staff Template</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-50 text-slate-900">
    <div class="max-w-5xl mx-auto px-6 py-10">
        <div class="mb-8">
            <p class="text-sm font-semibold text-indigo-600">Company Staff Templates</p>
            <h1 class="text-3xl font-semibold tracking-tight mt-2">Upload and download staff Excel templates</h1>
            <p class="text-slate-600 mt-2">Test template upload and download for a specific company.</p>
        </div>

        @if (session('error'))
            <div class="mb-8 rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-rose-700">
                {{ session('error') }}
            </div>
        @endif
        <div class="grid gap-6 md:grid-cols-2">
            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-semibold">Upload template</h2>
                <p class="text-sm text-slate-600 mt-1">Upload an Excel template for the selected company.</p>

                <form id="upload-form" class="mt-6 space-y-4" enctype="multipart/form-data">
                    <div>
                        <label for="templateType" class="block text-sm font-medium text-slate-700">Template type</label>
                        <select id="templateType" name="type" required
                            class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="excel">Excel</option>
                            <option value="pdf">PDF</option>
                        </select>
                    </div>
                    <div>
                        <label for="companyIdUpload" class="block text-sm font-medium text-slate-700">Company ID</label>
                        <input id="companyIdUpload" name="companyId" type="number" min="1" required value="{{ request()->query('company') }}"
                            class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <div>
                        <label for="templateFile" class="block text-sm font-medium text-slate-700">Excel file</label>
                        <input id="templateFile" name="template" type="file" accept=".xlsx,.xls" required
                            class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2" />
                    </div>
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-4 py-2 text-white font-medium shadow-sm hover:bg-indigo-500">
                        Upload template
                    </button>
                </form>

                <div id="upload-result" class="mt-4 text-sm"></div>
                <div class="mt-4 text-sm text-slate-500">
                    <a id="mapping-link" href="#" class="font-medium text-indigo-600 hover:text-indigo-500">Map template labels</a>
                </div>
            </div>

            <div class="bg-white rounded-2xl shadow-sm border border-slate-200 p-6">
                <h2 class="text-lg font-semibold">Download populated template</h2>
                <p class="text-sm text-slate-600 mt-1">Download the template filled with staff data.</p>

                <form id="download-form" class="mt-6 space-y-4">
                    <div>
                        <label for="companyIdDownload" class="block text-sm font-medium text-slate-700">Company ID</label>
                        <input id="companyIdDownload" name="companyId" type="number" min="1" required value="{{ request()->query('company') }}"
                            class="mt-2 w-full rounded-lg border border-slate-300 px-3 py-2 focus:outline-none focus:ring-2 focus:ring-indigo-500" />
                    </div>
                    <button type="submit"
                        class="inline-flex items-center justify-center rounded-lg bg-emerald-600 px-4 py-2 text-white font-medium shadow-sm hover:bg-emerald-500">
                        Download populated template
                    </button>
                </form>

                <div class="mt-6 rounded-lg bg-slate-50 border border-slate-200 p-4">
                    <p class="text-sm text-slate-600">The download will open in a new tab. Ensure you have staff records for the company.</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        const uploadForm = document.getElementById('upload-form');
        const uploadResult = document.getElementById('upload-result');

        uploadForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            uploadResult.textContent = 'Uploading...';
            uploadResult.className = 'mt-4 text-sm text-slate-600';

            const companyId = document.getElementById('companyIdUpload').value;
            const formData = new FormData(uploadForm);

            try {
                const response = await fetch(`/companies/${companyId}/template`, {
                    method: 'POST',
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
                    },
                });

                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Upload failed');
                }

                uploadResult.textContent = data.message || 'Template uploaded successfully. Redirecting to mapping...';
                uploadResult.className = 'mt-4 text-sm text-emerald-600';

                if (data.mapping_url) {
                    setTimeout(() => {
                        window.location.href = data.mapping_url;
                    }, 500);
                }
            } catch (error) {
                uploadResult.textContent = error.message;
                uploadResult.className = 'mt-4 text-sm text-rose-600';
            }
        });

        const downloadForm = document.getElementById('download-form');
        downloadForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const companyId = document.getElementById('companyIdDownload').value;
            window.open(`/companies/${companyId}/template`, '_blank');
        });

        function updateMappingLink() {
            const companyId = document.getElementById('companyIdUpload').value || document.getElementById('companyIdDownload').value;
            const link = document.getElementById('mapping-link');
            link.href = companyId ? `/companies/${companyId}/template/mapping` : '#';
        }

        document.getElementById('companyIdUpload').addEventListener('input', updateMappingLink);
        document.getElementById('companyIdDownload').addEventListener('input', updateMappingLink);
        updateMappingLink();
    </script>
</body>
</html>
