<nav class="topbar navbar navbar-expand-lg px-3 px-lg-4 py-2">
    <div class="container-fluid px-0">
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <button class="btn btn-outline-primary border-0 shadow-none" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
                <i class="bi bi-list fs-4"></i>
            </button>

            <div class="d-flex align-items-center gap-2">
                <img src="{{ asset('assets/logo2.png') }}" alt="S&IB company logo" class="topbar-company-logo">

                <div>
                    <div class="text-uppercase small fw-semibold text-primary-emphasis">Tan-MC ERP</div>
                    <div class="text-muted" style="font-size: 0.76rem;">Role-aware enterprise operations workspace</div>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-2 ms-auto">
            <div class="global-search d-none d-md-block position-relative">
                <div class="d-flex align-items-center bg-light rounded-pill px-3 py-1 border">
                <i class="bi bi-search text-muted me-2"></i>
                    <input
                        type="text"
                        class="form-control form-control-sm border-0 bg-transparent shadow-none p-0"
                        placeholder="Search clients, locations, contracts, service orders"
                        data-global-search-input
                        autocomplete="off"
                    >
                </div>

                <div class="global-search-results card border-0 shadow-sm position-absolute end-0 mt-2 d-none" data-global-search-results>
                    <div class="card-body p-2">
                        <div class="small text-muted px-2 py-1">Start typing to search.</div>
                    </div>
                </div>
            </div>

            <a href="{{ route('background-tasks.index') }}" class="btn btn-light border d-inline-flex align-items-center gap-2 px-3 py-1">
                <i class="bi bi-clock-history"></i>
                <span class="d-none d-lg-inline">Background Tasks</span>
            </a>

            <div class="dropdown">
                <button class="btn btn-light border dropdown-toggle d-flex align-items-center gap-2 px-2 px-lg-3 py-1" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-semibold" style="width: 2rem; height: 2rem; background: linear-gradient(135deg, #1f5eff, #0f2747); font-size: 0.82rem;">
                        {{ strtoupper(substr(Auth::user()->name ?? 'TM', 0, 2)) }}
                    </span>
                    <span class="text-start d-none d-sm-block">
                        <span class="d-block fw-semibold text-dark" style="font-size: 0.92rem;">{{ Auth::user()->name ?? 'Tan-MC User' }}</span>
                        <span class="d-block small text-muted">{{ Auth::user()->employee_code ?? Auth::user()->email ?? 'EMP0001' }} | {{ Auth::user()?->roleNames() ?: 'Viewer' }}</span>
                    </span>
                </button>

                <ul class="dropdown-menu dropdown-menu-end shadow border-0">
                    <li><a class="dropdown-item" href="{{ route('profile.edit') }}">Profile</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item">Sign out</button>
                        </form>
                    </li>
                </ul>
            </div>
        </div>
    </div>
</nav>
