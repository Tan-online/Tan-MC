@props([
    'columns' => 5,
    'rows' => 5,
])

<div class="table-loading-skeleton d-none" aria-hidden="true">
    <div class="border rounded-4 p-3 p-lg-4 bg-white">
        <div class="d-flex flex-column gap-3">
            @for ($row = 0; $row < $rows; $row++)
                <div class="row g-3 align-items-center">
                    @for ($column = 0; $column < $columns; $column++)
                        <div class="col">
                            <span class="placeholder col-12 rounded-3" style="height: 1.1rem;"></span>
                        </div>
                    @endfor
                </div>
            @endfor
        </div>
    </div>
</div>