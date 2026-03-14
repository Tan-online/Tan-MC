@php
    $user = request()->user();
    $dashboardRole = $user?->dashboardRole() ?? 'viewer';

    $menuGroups = [
        [
            'items' => [
                ['label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill', 'route' => 'dashboard', 'roles' => ['super_admin', 'admin', 'operations', 'reviewer', 'viewer']],
            ],
        ],
        [
            'title' => 'Master Data',
            'items' => [
                ['label' => 'Departments', 'icon' => 'bi-diagram-3-fill', 'route' => 'departments.index', 'roles' => ['super_admin', 'admin']],
                ['label' => 'States', 'icon' => 'bi-map-fill', 'route' => 'states.index', 'roles' => ['super_admin', 'admin']],
                ['label' => 'Operation Areas', 'icon' => 'bi-bounding-box-circles', 'route' => 'operation-areas.index', 'roles' => ['super_admin', 'admin']],
                ['label' => 'Teams', 'icon' => 'bi-people-fill', 'route' => 'teams.index', 'roles' => ['super_admin', 'admin']],
                ['label' => 'Users', 'icon' => 'bi-person-badge-fill', 'route' => 'users.index', 'roles' => ['super_admin', 'admin']],
            ],
        ],
        [
            'title' => 'Client Structure',
            'items' => [
                ['label' => 'Clients', 'icon' => 'bi-buildings-fill', 'route' => 'clients.index', 'roles' => ['super_admin', 'admin', 'viewer']],
                ['label' => 'Locations', 'icon' => 'bi-geo-alt-fill', 'route' => 'locations.index', 'roles' => ['super_admin', 'admin', 'operations', 'viewer']],
                ['label' => 'Contracts', 'icon' => 'bi-file-earmark-text-fill', 'route' => 'contracts.index', 'roles' => ['super_admin', 'admin', 'operations', 'viewer']],
                ['label' => 'Service Orders', 'icon' => 'bi-clipboard2-check-fill', 'route' => 'service-orders.index', 'roles' => ['super_admin', 'admin', 'operations', 'viewer']],
            ],
        ],
        [
            'title' => 'Mapping',
            'items' => [
                ['label' => 'Executive Mapping', 'icon' => 'bi-person-badge-fill', 'route' => 'executive-mappings.index', 'roles' => ['super_admin', 'admin']],
                ['label' => 'Executive Replacement', 'icon' => 'bi-arrow-repeat', 'route' => 'executive-replacements.index', 'roles' => ['super_admin', 'admin']],
            ],
        ],
        [
            'title' => 'Operations',
            'items' => [
                ['label' => 'Dispatch Entry', 'icon' => 'bi-truck', 'href' => '#', 'roles' => ['super_admin', 'admin', 'operations']],
                ['label' => 'Bulk Receive', 'icon' => 'bi-inboxes-fill', 'route' => 'bulk-receive.index', 'roles' => ['super_admin', 'admin', 'operations', 'reviewer']],
            ],
        ],
        [
            'title' => 'Workflow',
            'items' => [
                ['label' => 'Review / Approval', 'icon' => 'bi-shield-check', 'route' => 'bulk-receive.index', 'roles' => ['super_admin', 'admin', 'reviewer']],
            ],
        ],
        [
            'title' => 'Reports',
            'items' => [
                ['label' => 'Reports', 'icon' => 'bi-bar-chart-fill', 'route' => 'reports.index', 'roles' => ['super_admin', 'admin', 'reviewer', 'viewer']],
            ],
        ],
    ];

    $menuGroups = collect($menuGroups)
        ->map(function (array $group) use ($dashboardRole) {
            $group['items'] = collect($group['items'])
                ->filter(fn (array $item) => in_array($dashboardRole, $item['roles'] ?? ['viewer'], true))
                ->values()
                ->all();

            return $group;
        })
        ->filter(fn (array $group) => !empty($group['items']))
        ->values();

    $roleLabel = str_replace('_', ' ', $dashboardRole);
@endphp

<aside class="sidebar d-flex flex-column flex-shrink-0 p-3 p-lg-4">
    <div class="sidebar-brand d-flex align-items-center justify-content-center mb-4 pb-3 border-bottom border-light border-opacity-10">
        <img src="{{ asset('assets/logo.png') }}" alt="Tan-MC logo" class="logo-mark">
    </div>

    <div class="text-center text-white-50 small text-uppercase fw-semibold letter-spacing-wide mb-4">
        {{ $roleLabel }} workspace
    </div>

    <div class="sidebar-nav flex-grow-1 pe-1">
        @foreach ($menuGroups as $group)
            <div class="mb-4">
                @if (!empty($group['title']))
                    <div class="menu-group-title px-3 pb-2 mb-1">{{ $group['title'] }}</div>
                @endif

                <ul class="nav nav-pills flex-column gap-2">
                    @foreach ($group['items'] as $item)
                        @php
                            $isActive = isset($item['route']) && request()->routeIs($item['route']);
                            $target = isset($item['route']) ? route($item['route']) : ($item['href'] ?? '#');
                        @endphp

                        <li class="nav-item">
                            <a href="{{ $target }}" class="nav-link {{ $isActive ? 'active' : '' }}">
                                <i class="bi {{ $item['icon'] }}"></i>
                                <span class="menu-label text-truncate">{{ $item['label'] }}</span>
                                @if ($isActive)
                                    <i class="bi bi-dot menu-caret ms-auto"></i>
                                @endif
                            </a>
                        </li>
                    @endforeach
                </ul>
            </div>
        @endforeach
    </div>

    <div class="mt-auto pt-3 border-top border-light border-opacity-10 text-white-50 small">
        <div class="menu-label">Enterprise monitoring with role-based operational access.</div>
    </div>
</aside>
