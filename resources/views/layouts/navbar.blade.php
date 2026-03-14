<nav class="topbar navbar navbar-expand-lg px-3 px-lg-4 py-3">
    <div class="container-fluid px-0">
        <div class="d-flex align-items-center gap-2 gap-lg-3">
            <button class="btn btn-outline-primary border-0 shadow-none" type="button" data-sidebar-toggle aria-label="Toggle sidebar">
                <i class="bi bi-list fs-4"></i>
            </button>

            <div class="d-flex align-items-center gap-3">
                <img src="{{ asset('assets/logo2.png') }}" alt="S&IB company logo" class="topbar-company-logo">

                <div>
                <div class="text-uppercase small fw-semibold text-primary-emphasis">Tan-MC Admin</div>
                <div class="text-muted small">Operational visibility for enterprise teams</div>
                </div>
            </div>
        </div>

        <div class="d-flex align-items-center gap-3 ms-auto">
            <div class="d-none d-md-flex align-items-center bg-light rounded-pill px-3 py-2 border">
                <i class="bi bi-search text-muted me-2"></i>
                <input type="text" class="form-control form-control-sm border-0 bg-transparent shadow-none p-0" placeholder="Search modules, reports, teams">
            </div>

            <div class="dropdown">
                <button class="btn btn-light border dropdown-toggle d-flex align-items-center gap-2 px-2 px-lg-3 py-2" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <span class="d-inline-flex align-items-center justify-content-center rounded-circle text-white fw-semibold" style="width: 2.25rem; height: 2.25rem; background: linear-gradient(135deg, #1f5eff, #0f2747);">
                        {{ strtoupper(substr(Auth::user()->name ?? 'TM', 0, 2)) }}
                    </span>
                    <span class="text-start d-none d-sm-block">
                        <span class="d-block fw-semibold text-dark">{{ Auth::user()->name ?? 'Tan-MC User' }}</span>
                        <span class="d-block small text-muted">{{ Auth::user()->employee_code ?? Auth::user()->email ?? 'EMP0001' }}</span>
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
