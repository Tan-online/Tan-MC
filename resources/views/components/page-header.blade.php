@props([
    'title',
    'subtitle' => null,
    'breadcrumbs' => [],
])

<div class="page-header">
    <div class="page-header-copy">
        <h1 class="page-title">{{ $title }}</h1>

        @if ($subtitle)
            <p class="page-subtitle">{{ $subtitle }}</p>
        @endif

        @if ($breadcrumbs !== [])
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb mb-0">
                    @foreach ($breadcrumbs as $breadcrumb)
                        @php
                            $label = is_array($breadcrumb) ? ($breadcrumb['label'] ?? '') : (string) $breadcrumb;
                            $url = is_array($breadcrumb) ? ($breadcrumb['url'] ?? null) : null;
                        @endphp

                        <li class="breadcrumb-item {{ $loop->last ? 'active' : '' }}" @if ($loop->last) aria-current="page" @endif>
                            @if ($url && ! $loop->last)
                                <a class="text-decoration-none" href="{{ $url }}">{{ $label }}</a>
                            @else
                                {{ $label }}
                            @endif
                        </li>
                    @endforeach
                </ol>
            </nav>
        @endif
    </div>

    @isset($actions)
        <div class="page-header-actions">
            {{ $actions }}
        </div>
    @endisset
</div>