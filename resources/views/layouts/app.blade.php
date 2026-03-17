<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        @php
            $appName = config('app.name', 'Tan-MC');
            $pageTitle = trim($__env->yieldContent('title', $appName));
        @endphp

        <title>{{ $pageTitle }}</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        @vite(['resources/css/app.css', 'resources/js/app.js'])

        <style>
            :root {
                --tmc-sidebar-width: 232px;
                --tmc-sidebar-collapsed-width: 76px;
                --tmc-topbar-height: 64px;
                --tmc-content-padding: 14px;
                --tmc-sidebar-bg: #0f2a44;
                --tmc-sidebar-accent: #173a68;
                --tmc-topbar-bg: #ffffff;
                --tmc-body-bg: #f4f7fb;
                --tmc-text-muted: #6c7a89;
                --tmc-border: #dbe4f0;
                --tmc-card-shadow: 0 10px 26px rgba(15, 39, 71, 0.06);
                --tmc-highlight: #1f5eff;
            }

            html,
            body {
                height: 100%;
                margin: 0;
                font-family: 'Figtree', sans-serif;
                background:
                    radial-gradient(circle at top left, rgba(31, 94, 255, 0.06), transparent 22%),
                    linear-gradient(180deg, #f8fbff 0%, var(--tmc-body-bg) 100%);
                color: #1b2a3a;
                overflow: hidden;
            }

            .app-layout {
                display: flex;
                height: 100vh;
                overflow: hidden;
            }

            .sidebar {
                width: var(--tmc-sidebar-width);
                height: 100vh;
                background: var(--tmc-sidebar-bg);
                position: fixed;
                left: 0;
                top: 0;
                display: flex;
                flex-direction: column;
                overflow: hidden;
                transition: width 0.25s ease, transform 0.25s ease;
                box-shadow: 16px 0 30px rgba(8, 20, 38, 0.12);
                z-index: 1035;
            }

            .sidebar .logo-mark {
                width: 118px;
                height: auto;
                object-fit: contain;
                margin-bottom: 0;
                filter: drop-shadow(0 10px 18px rgba(0, 0, 0, 0.22));
            }

            .sidebar-brand {
                min-height: 76px;
            }

            .sidebar-nav {
                min-height: 0;
                overflow-x: hidden;
                overflow-y: auto;
                scrollbar-gutter: stable;
                padding-right: 0.2rem;
                margin-right: -0.2rem;
            }

            .sidebar-nav::-webkit-scrollbar {
                width: 6px;
            }

            .sidebar-nav::-webkit-scrollbar-thumb {
                background: rgba(255, 255, 255, 0.16);
                border-radius: 999px;
            }

            .sidebar-footer {
                flex-shrink: 0;
            }

            .menu-group-block:last-child {
                margin-bottom: 0.5rem !important;
            }

            .topbar-company-logo {
                height: 40px;
                width: auto;
                object-fit: contain;
            }

            .global-search {
                width: min(24rem, 34vw);
            }

            .global-search-results {
                width: min(30rem, calc(100vw - 3rem));
                max-height: 22rem;
                overflow-y: auto;
                z-index: 1045;
            }

            .global-search-group + .global-search-group {
                border-top: 1px solid rgba(219, 228, 240, 0.9);
                margin-top: 0.35rem;
                padding-top: 0.35rem;
            }

            .global-search-link {
                display: flex;
                align-items: flex-start;
                justify-content: space-between;
                gap: 0.75rem;
                padding: 0.5rem 0.65rem;
                border-radius: 10px;
                color: inherit;
                text-decoration: none;
            }

            .global-search-link:hover {
                background: rgba(31, 94, 255, 0.08);
            }

            .brand-copy,
            .menu-label,
            .menu-group-title,
            .menu-caret {
                transition: opacity 0.2s ease;
            }

            .menu-group-title {
                color: rgba(255, 255, 255, 0.46);
                font-size: 0.64rem;
                font-weight: 700;
                letter-spacing: 0.12em;
                text-transform: uppercase;
            }

            .sidebar-nav .nav-link {
                color: rgba(255, 255, 255, 0.78);
                border-radius: 10px;
                padding: 0.56rem 0.72rem;
                display: flex;
                align-items: center;
                gap: 0.62rem;
                font-weight: 500;
                font-size: 0.88rem;
                line-height: 1.15;
                transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
            }

            .sidebar-nav .nav-link:hover,
            .sidebar-nav .nav-link.active {
                color: #fff;
                background: linear-gradient(90deg, rgba(255, 255, 255, 0.16), rgba(31, 94, 255, 0.28));
                transform: translateX(2px);
            }

            .sidebar-nav .nav-link i {
                font-size: 1rem;
                width: 1.35rem;
                text-align: center;
            }

            .topbar {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(12px);
                border-bottom: 1px solid rgba(219, 228, 240, 0.9);
                min-height: var(--tmc-topbar-height);
                flex-shrink: 0;
            }

            .topbar .navbar-brand,
            .topbar .text-muted,
            .topbar .small {
                line-height: 1.2;
            }

            .main-wrapper {
                margin-left: var(--tmc-sidebar-width);
                display: flex;
                flex-direction: column;
                width: calc(100% - var(--tmc-sidebar-width));
                height: 100vh;
                min-width: 0;
                transition: margin-left 0.25s ease, width 0.25s ease;
            }

            .content-area {
                padding: var(--tmc-content-padding);
                flex: 1;
                min-height: 0;
                overflow-y: auto;
                overflow-x: hidden;
            }

            .content-area .row {
                --bs-gutter-x: 1rem;
            }

            .surface-card,
            .card {
                background: #ffffff;
                border: 1px solid rgba(219, 228, 240, 0.9);
                box-shadow: var(--tmc-card-shadow);
                border-radius: 10px;
            }

            .card {
                padding: 14px;
            }

            .surface-card.p-4,
            .card.p-4 {
                padding: 1rem !important;
            }

            .surface-card.p-3,
            .card.p-3 {
                padding: 0.88rem !important;
            }

            .metric-card {
                position: relative;
                overflow: hidden;
                min-height: 84px;
                padding: 0.9rem !important;
            }

            .metric-card::after {
                content: "";
                position: absolute;
                inset: auto -2rem -2rem auto;
                width: 6rem;
                height: 6rem;
                border-radius: 50%;
                background: radial-gradient(circle, rgba(31, 94, 255, 0.18), transparent 65%);
            }

            .metric-icon {
                width: 36px;
                height: 36px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 10px;
                background: rgba(31, 94, 255, 0.12);
                color: var(--tmc-highlight);
            }

            .metric-card .display-6 {
                font-size: 1.35rem;
                line-height: 1.05;
            }

            .metric-card .text-muted.small,
            .metric-card .small {
                font-size: 0.71rem !important;
            }

            .page-header {
                display: flex;
                flex-direction: column;
                gap: 0.6rem;
                margin-bottom: 0.85rem;
            }

            .page-header-copy {
                min-width: 0;
            }

            .page-title {
                margin: 0 0 0.2rem;
                font-size: 1.24rem;
                font-weight: 700;
                line-height: 1.2;
            }

            .page-subtitle {
                margin: 0 0 0.32rem;
                color: var(--tmc-text-muted);
                font-size: 0.84rem;
            }

            .page-header-actions {
                display: flex;
                justify-content: flex-start;
            }

            .action-buttons {
                justify-content: flex-start;
            }

            .table-panel-head {
                display: flex;
                flex-direction: column;
                gap: 0.6rem;
                margin-bottom: 0.85rem;
            }

            .table-panel-title {
                margin: 0 0 0.2rem;
                font-size: 0.96rem;
                font-weight: 700;
            }

            .table-panel-description {
                margin: 0;
                color: var(--tmc-text-muted);
                font-size: 0.82rem;
            }

            .table-panel-toolbar {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
                align-items: center;
            }

            .table-panel-footer {
                display: flex;
                flex-direction: column;
                gap: 0.6rem;
                align-items: flex-start;
                margin-top: 0.85rem;
            }

            .breadcrumb {
                --bs-breadcrumb-divider-color: #90a0b4;
                --bs-breadcrumb-item-active-color: #5a6a7f;
                margin-bottom: 0;
                font-size: 0.76rem;
            }

            .btn {
                --bs-btn-padding-y: 0.46rem;
                --bs-btn-padding-x: 0.82rem;
                --bs-btn-font-size: 0.87rem;
                --bs-btn-border-radius: 10px;
            }

            .btn-sm {
                --bs-btn-padding-y: 0.28rem;
                --bs-btn-padding-x: 0.58rem;
                --bs-btn-font-size: 0.77rem;
            }

            .form-label {
                margin-bottom: 0.28rem;
                font-size: 0.77rem;
                font-weight: 600;
                color: #536274;
            }

            .form-control,
            .form-select {
                min-height: 38px;
                padding: 0.45rem 0.72rem;
                font-size: 0.88rem;
                border-radius: 10px;
            }

            .input-group-text {
                padding: 0.45rem 0.72rem;
                font-size: 0.84rem;
                border-radius: 10px;
            }

            .form-control::placeholder {
                color: #8ea0b5;
            }

            .pagination {
                margin-bottom: 0;
            }

            .table-responsive {
                overflow-y: visible;
            }

            .table {
                font-size: 12.5px;
                margin-bottom: 0;
            }

            .table > :not(caption) > * > * {
                padding: 7px 8px;
                border-bottom-color: rgba(219, 228, 240, 0.9);
                vertical-align: middle;
            }

            .table thead th {
                color: #6f8197;
                text-transform: uppercase;
                font-size: 0.7rem;
                letter-spacing: 0.06em;
            }

            .modal .modal-header,
            .modal .modal-footer {
                padding: 0.9rem 1rem;
            }

            .modal .modal-body {
                padding: 1rem;
            }

            .modal .row {
                --bs-gutter-x: 0.9rem;
                --bs-gutter-y: 0.9rem;
            }

            .table-loading-shell.is-loading .table-loading-skeleton {
                display: block !important;
            }

            .table-loading-shell.is-loading .table-content {
                display: none;
            }

            .flash-messages {
                position: fixed;
                top: calc(var(--tmc-topbar-height) + 0.75rem);
                right: 1rem;
                z-index: 1080;
                width: min(28rem, calc(100vw - 2rem));
                display: grid;
                gap: 0.75rem;
                pointer-events: none;
            }

            .flash-messages .alert {
                margin-bottom: 0;
                border: 0;
                border-radius: 12px;
                box-shadow: 0 10px 24px rgba(15, 39, 71, 0.16);
                pointer-events: auto;
            }

            .sidebar-backdrop {
                display: none;
            }

            body.sidebar-collapsed .sidebar {
                width: var(--tmc-sidebar-collapsed-width);
            }

            body.sidebar-collapsed .main-wrapper {
                margin-left: var(--tmc-sidebar-collapsed-width);
                width: calc(100% - var(--tmc-sidebar-collapsed-width));
            }

            body.sidebar-collapsed .brand-copy,
            body.sidebar-collapsed .menu-label,
            body.sidebar-collapsed .menu-group-title,
            body.sidebar-collapsed .menu-caret {
                opacity: 0;
                pointer-events: none;
                width: 0;
                overflow: hidden;
            }

            body.sidebar-collapsed .sidebar .nav-link {
                justify-content: center;
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }

            body.sidebar-collapsed .sidebar .logo-mark {
                width: 48px;
            }

            @media (max-width: 991.98px) {
                .sidebar {
                    transform: translateX(-100%);
                }

                body.sidebar-open .sidebar {
                    transform: translateX(0);
                }

                .sidebar-backdrop {
                    position: fixed;
                    inset: 0;
                    background: rgba(7, 18, 33, 0.45);
                    z-index: 1030;
                }

                body.sidebar-open .sidebar-backdrop {
                    display: block;
                }

                .main-wrapper {
                    margin-left: 0;
                    width: 100%;
                }

                .content-area {
                    padding: 0.9rem;
                }

                .flash-messages {
                    top: calc(var(--tmc-topbar-height) + 0.5rem);
                    right: 0.75rem;
                    width: calc(100vw - 1.5rem);
                }

                .sidebar .logo-mark {
                    width: 112px;
                }

                .surface-card.p-4,
                .card.p-4 {
                    padding: 0.9rem !important;
                }

                body.sidebar-collapsed .sidebar {
                    width: var(--tmc-sidebar-width);
                }

                body.sidebar-collapsed .main-wrapper {
                    margin-left: 0;
                    width: 100%;
                }

                body.sidebar-collapsed .brand-copy,
                body.sidebar-collapsed .menu-label,
                body.sidebar-collapsed .menu-group-title,
                body.sidebar-collapsed .menu-caret {
                    opacity: 1;
                    width: auto;
                }
            }

            @media (min-width: 992px) {
                .page-header {
                    flex-direction: row;
                    align-items: flex-start;
                    justify-content: space-between;
                }

                .page-header-actions,
                .action-buttons {
                    justify-content: flex-end;
                }

                .table-panel-head {
                    flex-direction: row;
                    align-items: center;
                    justify-content: space-between;
                }

                .table-panel-footer {
                    flex-direction: row;
                    align-items: center;
                    justify-content: space-between;
                }
            }
        </style>

        @stack('styles')
    </head>
    <body>
        <div class="app-layout">
            @include('layouts.sidebar')

            <div class="sidebar-backdrop" data-sidebar-close></div>

            <div class="main-wrapper">
                @include('layouts.navbar')

                <main class="content-area">
                    @include('partials.flash-messages')

                    @hasSection('content')
                        @yield('content')
                    @else
                        @isset($header)
                            <x-page-header
                                :title="strip_tags($header)"
                                :breadcrumbs="[
                                    ['label' => 'Home', 'url' => route('dashboard')],
                                    ['label' => strip_tags($header)],
                                ]"
                            />
                        @endisset

                        {{ $slot ?? '' }}
                    @endif
                </main>
            </div>
        </div>

        <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                const body = document.body;

                document.querySelectorAll('[data-sidebar-toggle]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        if (window.innerWidth < 992) {
                            body.classList.toggle('sidebar-open');
                            return;
                        }

                        body.classList.toggle('sidebar-collapsed');
                    });
                });

                document.querySelectorAll('[data-sidebar-close]').forEach(function (button) {
                    button.addEventListener('click', function () {
                        body.classList.remove('sidebar-open');
                    });
                });

                const tableSearch = document.querySelector('[data-table-search]');
                const tableRows = Array.from(document.querySelectorAll('[data-search-row]'));

                if (tableSearch && tableRows.length) {
                    tableSearch.addEventListener('input', function (event) {
                        const query = event.target.value.trim().toLowerCase();

                        tableRows.forEach(function (row) {
                            const text = row.textContent.toLowerCase();
                            row.classList.toggle('d-none', !text.includes(query));
                        });
                    });
                }

                const reopenModalId = @json(session('open_modal'));

                const searchInput = document.querySelector('[data-global-search-input]');
                const searchResults = document.querySelector('[data-global-search-results]');
                let searchAbortController = null;

                if (searchInput && searchResults) {
                    const renderSearchResults = function (groups) {
                        if (!groups.length) {
                            searchResults.innerHTML = '<div class="card-body p-2"><div class="small text-muted px-2 py-1">No matches found.</div></div>';
                            searchResults.classList.remove('d-none');

                            return;
                        }

                        searchResults.innerHTML = '<div class="card-body p-2">' + groups.map(function (group) {
                            return '<div class="global-search-group">'
                                + '<div class="px-2 pt-1 pb-2 small text-uppercase text-muted fw-semibold">' + group.module + '</div>'
                                + group.items.map(function (item) {
                                    return '<a class="global-search-link" href="' + item.url + '">'
                                        + '<span><span class="d-block fw-semibold text-dark">' + item.label + '</span>'
                                        + '<span class="d-block small text-muted">' + (item.meta || '') + '</span></span>'
                                        + '<i class="bi bi-arrow-up-right small text-muted"></i>'
                                        + '</a>';
                                }).join('')
                                + '</div>';
                        }).join('') + '</div>';

                        searchResults.classList.remove('d-none');
                    };

                    const clearSearchResults = function () {
                        searchResults.classList.add('d-none');
                    };

                    searchInput.addEventListener('input', function (event) {
                        const query = event.target.value.trim();

                        if (query.length < 2) {
                            clearSearchResults();
                            return;
                        }

                        if (searchAbortController) {
                            searchAbortController.abort();
                        }

                        searchAbortController = new AbortController();

                        fetch('{{ route('search.global') }}?q=' + encodeURIComponent(query), {
                            signal: searchAbortController.signal,
                            headers: {
                                'Accept': 'application/json',
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                        })
                            .then(function (response) {
                                return response.ok ? response.json() : { results: [] };
                            })
                            .then(function (payload) {
                                renderSearchResults(payload.results || []);
                            })
                            .catch(function (error) {
                                if (error.name !== 'AbortError') {
                                    clearSearchResults();
                                }
                            });
                    });

                    document.addEventListener('click', function (event) {
                        if (!searchResults.contains(event.target) && event.target !== searchInput) {
                            clearSearchResults();
                        }
                    });
                }

                if (reopenModalId) {
                    const reopenModalElement = document.getElementById(reopenModalId);

                    if (reopenModalElement) {
                        bootstrap.Modal.getOrCreateInstance(reopenModalElement).show();
                    }
                }

                document.querySelectorAll('.auto-dismiss').forEach(function (element) {
                    window.setTimeout(function () {
                        if (!element.isConnected) {
                            return;
                        }

                        bootstrap.Alert.getOrCreateInstance(element).close();

                        window.setTimeout(function () {
                            if (element.isConnected) {
                                element.remove();
                            }
                        }, 150);
                    }, 4000);
                });

                const showLoadingState = function (element) {
                    const container = element?.closest('[data-loading-container]');

                    if (container) {
                        container.classList.add('is-loading');
                    }
                };

                document.querySelectorAll('[data-loading-form]').forEach(function (form) {
                    form.addEventListener('submit', function () {
                        showLoadingState(form);
                    });
                });

                document.addEventListener('click', function (event) {
                    const trigger = event.target.closest('.pagination a, [data-loading-trigger]');

                    if (trigger) {
                        if (trigger.dataset.loadingMode === 'download') {
                            return;
                        }

                        showLoadingState(trigger);
                    }
                });
            });
        </script>

        @stack('scripts')
    </body>
</html>
