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
                --tmc-sidebar-width: 260px;
                --tmc-sidebar-collapsed-width: 84px;
                --tmc-topbar-height: 78px;
                --tmc-sidebar-bg: #0f2747;
                --tmc-sidebar-accent: #173a68;
                --tmc-topbar-bg: #ffffff;
                --tmc-body-bg: #f4f7fb;
                --tmc-text-muted: #6c7a89;
                --tmc-border: #dbe4f0;
                --tmc-card-shadow: 0 16px 40px rgba(15, 39, 71, 0.08);
                --tmc-highlight: #1f5eff;
            }

            body {
                font-family: 'Figtree', sans-serif;
                background:
                    radial-gradient(circle at top left, rgba(31, 94, 255, 0.08), transparent 22%),
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
                background: linear-gradient(180deg, #102746 0%, #0b1c33 100%);
                position: fixed;
                inset: 0 auto 0 0;
                overflow: hidden;
                transition: width 0.25s ease, transform 0.25s ease;
                box-shadow: 16px 0 30px rgba(8, 20, 38, 0.12);
                z-index: 1035;
            }

            .sidebar .logo-mark {
                width: 138px;
                height: auto;
                object-fit: contain;
                margin-bottom: 0;
                filter: drop-shadow(0 10px 18px rgba(0, 0, 0, 0.22));
            }

            .sidebar-brand {
                min-height: 124px;
            }

            .topbar-company-logo {
                height: 40px;
                width: auto;
                object-fit: contain;
            }

            .brand-copy,
            .menu-label,
            .menu-group-title,
            .menu-caret {
                transition: opacity 0.2s ease;
            }

            .menu-group-title {
                color: rgba(255, 255, 255, 0.46);
                font-size: 0.7rem;
                font-weight: 700;
                letter-spacing: 0.12em;
                text-transform: uppercase;
            }

            .sidebar-nav .nav-link {
                color: rgba(255, 255, 255, 0.78);
                border-radius: 14px;
                padding: 0.85rem 1rem;
                display: flex;
                align-items: center;
                gap: 0.85rem;
                font-weight: 500;
                transition: background-color 0.2s ease, color 0.2s ease, transform 0.2s ease;
            }

            .sidebar-nav .nav-link:hover,
            .sidebar-nav .nav-link.active {
                color: #fff;
                background: linear-gradient(90deg, rgba(255, 255, 255, 0.16), rgba(31, 94, 255, 0.28));
                transform: translateX(2px);
            }

            .sidebar-nav .nav-link i {
                font-size: 1.1rem;
                width: 1.5rem;
                text-align: center;
            }

            .topbar {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(12px);
                border-bottom: 1px solid rgba(219, 228, 240, 0.9);
                min-height: var(--tmc-topbar-height);
                flex-shrink: 0;
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
                padding: 25px;
                flex: 1;
                overflow-y: auto;
                overflow-x: hidden;
            }

            .surface-card {
                background: rgba(255, 255, 255, 0.92);
                border: 1px solid rgba(219, 228, 240, 0.9);
                box-shadow: var(--tmc-card-shadow);
                border-radius: 24px;
            }

            .metric-card {
                position: relative;
                overflow: hidden;
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
                width: 52px;
                height: 52px;
                display: inline-flex;
                align-items: center;
                justify-content: center;
                border-radius: 16px;
                background: rgba(31, 94, 255, 0.12);
                color: var(--tmc-highlight);
            }

            .breadcrumb {
                --bs-breadcrumb-divider-color: #90a0b4;
                --bs-breadcrumb-item-active-color: #5a6a7f;
            }

            .table > :not(caption) > * > * {
                padding-top: 0.95rem;
                padding-bottom: 0.95rem;
                border-bottom-color: rgba(219, 228, 240, 0.9);
            }

            .table thead th {
                color: #6f8197;
                text-transform: uppercase;
                font-size: 0.76rem;
                letter-spacing: 0.06em;
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
                    padding: 1rem;
                }

                .sidebar .logo-mark {
                    width: 118px;
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
                    @if (session('status'))
                        <div class="alert alert-success alert-dismissible fade show border-0 shadow-sm" role="alert">
                            {{ session('status') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if (session('error'))
                        <div class="alert alert-danger alert-dismissible fade show border-0 shadow-sm" role="alert">
                            {{ session('error') }}
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    @endif

                    @if ($errors->any())
                        <div class="alert alert-warning border-0 shadow-sm" role="alert">
                            <div class="fw-semibold mb-2">Please review the highlighted form details.</div>
                            <ul class="mb-0 ps-3">
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @hasSection('content')
                        @yield('content')
                    @else
                        @isset($header)
                            <div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3 mb-4">
                                <div>
                                    <h1 class="h3 fw-semibold mb-1">{{ strip_tags($header) }}</h1>
                                    <nav aria-label="breadcrumb">
                                        <ol class="breadcrumb mb-0">
                                            <li class="breadcrumb-item"><a class="text-decoration-none" href="{{ route('dashboard') }}">Home</a></li>
                                            <li class="breadcrumb-item active" aria-current="page">{{ strip_tags($header) }}</li>
                                        </ol>
                                    </nav>
                                </div>
                            </div>
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

                if (reopenModalId) {
                    const reopenModalElement = document.getElementById(reopenModalId);

                    if (reopenModalElement) {
                        bootstrap.Modal.getOrCreateInstance(reopenModalElement).show();
                    }
                }
            });
        </script>

        @stack('scripts')
    </body>
</html>
