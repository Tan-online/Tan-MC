@props([
    'title' => null,
    'description' => null,
    'loading' => false,
    'columns' => 5,
    'rows' => 5,
])

<div @class(['table-panel', 'surface-card', 'p-3', 'p-lg-4', 'table-loading-shell' => $loading]) @if ($loading) data-loading-container @endif>
    @if ($title || $description || isset($toolbar))
        <div class="table-panel-head">
            <div>
                @if ($title)
                    <h2 class="table-panel-title">{{ $title }}</h2>
                @endif

                @if ($description)
                    <p class="table-panel-description">{{ $description }}</p>
                @endif
            </div>

            @isset($toolbar)
                <div class="table-panel-toolbar">
                    {{ $toolbar }}
                </div>
            @endisset
        </div>
    @endif

    @if ($loading)
        <x-table-loading-skeleton :columns="$columns" :rows="$rows" />
    @endif

    <div @class(['table-content' => $loading])>
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="table-panel-footer">
            {{ $footer }}
        </div>
    @endisset
</div>