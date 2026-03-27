@php
    $user = request()->user();
    $dashboardRole = $user?->dashboardRole() ?? 'viewer';
    $access = app(\App\Services\AccessControlService::class);
    $menuGroups = config('erp.menu', []);

    if ($dashboardRole === 'operations' && $user) {
        $menuGroups = [
            [
                'title' => 'Operations Workspace',
                'items' => [
                    ['label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill', 'route' => 'dashboard'],
                ],
            ],
            [
                'title' => 'Operations',
                'items' => array_values(array_filter([
                    $access->isOperationsSupervisor($user)
                        ? ['label' => 'Teams', 'icon' => 'bi-people-fill', 'route' => 'operations-workspace.teams']
                        : null,
                    ['label' => 'Muster Roll Upload', 'icon' => 'bi-upload', 'route' => 'bulk-receive.index'],
                    ['label' => 'Bulk Upload', 'icon' => 'bi-inboxes-fill', 'route' => 'bulk-muster.index'],
                ])),
            ],
            [
                'title' => 'Reports',
                'items' => [
                    ['label' => 'Reports', 'icon' => 'bi-bar-chart-fill', 'route' => 'reports.index'],
                ],
            ],
        ];
    }

    $menuGroups = collect($menuGroups)
        ->map(function (array $group) {
            $group['items'] = collect($group['items'])
                ->filter(fn (array $item) => app(\App\Services\AccessControlService::class)->canViewMenuItem(request()->user(), $item))
                ->values()
                ->all();

            return $group;
        })
        ->filter(fn (array $group) => !empty($group['items']))
        ->values();

    $roleLabel = $user?->roleNames() ?: str_replace('_', ' ', $dashboardRole);
@endphp

<aside class="sidebar d-flex flex-column flex-shrink-0 p-3">
    <div class="sidebar-brand d-flex align-items-center justify-content-center mb-3 pb-3 border-bottom border-light border-opacity-10">
        <img src="{{ asset('assets/logo.png') }}" alt="Tan-MC logo" class="logo-mark">
    </div>

    <div class="text-center text-white-50 small text-uppercase fw-semibold letter-spacing-wide mb-3">
        {{ $roleLabel }} workspace
    </div>

    <div class="sidebar-nav flex-grow-1 pe-1">
        @foreach ($menuGroups as $group)
            <div class="menu-group-block mb-3">
                @if (!empty($group['title']))
                    <div class="menu-group-title px-3 pb-2 mb-1">{{ $group['title'] }}</div>
                @endif

                <ul class="nav nav-pills flex-column gap-1">
                    @foreach ($group['items'] as $item)
                        @php
                            $isActive = isset($item['route']) && request()->routeIs($item['route']);
                            $target = isset($item['route']) && \Illuminate\Support\Facades\Route::has($item['route'])
                                ? route($item['route'])
                                : ($item['href'] ?? '#');
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

    <div class="sidebar-footer mt-auto pt-3 border-top border-light border-opacity-10 text-white-50 small">
        <div class="menu-label">Enterprise monitoring with role-based operational access.</div>
    </div>
</aside>
