@php
    $modalId = $modalId ?? \Illuminate\Support\Str::camel($type) . 'ImportModal';
@endphp

<div class="d-flex flex-wrap gap-2">
    <a href="{{ route('imports.template', $type) }}" class="btn btn-outline-secondary">
        <i class="bi bi-download me-2"></i>Download Template
    </a>

    <button class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#{{ $modalId }}">
        <i class="bi bi-upload me-2"></i>Import Excel
    </button>

    <div class="modal fade" id="{{ $modalId }}" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <form method="POST" action="{{ route('imports.store', $type) }}" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" name="type" value="{{ $type }}">
                    <div class="modal-header">
                        <h2 class="modal-title h5 mb-0">Import {{ $label }}</h2>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-light border">
                            <div class="fw-semibold mb-1">Before uploading</div>
                            <div class="small text-muted">Use the template file so the heading names match exactly. Valid rows will be inserted, and invalid rows will appear in the error report below.</div>
                        </div>

                        <div>
                            <label class="form-label">Excel File</label>
                            <input type="file" name="import_file" accept=".xlsx,.xls,.csv" class="form-control @if($errors->has('import_file') && session('open_modal') === $modalId) is-invalid @endif" required>
                            <div class="form-text">Supported formats: XLSX, XLS, CSV. Imports run in chunks for large files.</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Start Import</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
