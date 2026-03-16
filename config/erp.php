<?php

return [
    'roles' => [
        [
            'name' => 'Super Admin',
            'slug' => 'super_admin',
            'description' => 'Owns the full ERP platform, access control, workflow governance, and audit oversight.',
        ],
        [
            'name' => 'Admin',
            'slug' => 'admin',
            'description' => 'Manages enterprise modules, operational workflows, and reporting without user administration.',
        ],
        [
            'name' => 'Operations',
            'slug' => 'operations',
            'description' => 'Executes day-to-day dispatch and muster submission operations.',
        ],
        [
            'name' => 'Reviewer',
            'slug' => 'reviewer',
            'description' => 'Reviews workflow queues, approves submissions, and returns exceptions.',
        ],
    ],

    'permissions' => [
        ['module' => 'dashboard', 'action' => 'view', 'description' => 'View the assigned dashboard.'],

        ['module' => 'departments', 'action' => 'view', 'description' => 'View departments.'],
        ['module' => 'departments', 'action' => 'create', 'description' => 'Create departments.'],
        ['module' => 'departments', 'action' => 'edit', 'description' => 'Edit departments.'],
        ['module' => 'departments', 'action' => 'delete', 'description' => 'Delete departments.'],

        ['module' => 'states', 'action' => 'view', 'description' => 'View states.'],
        ['module' => 'states', 'action' => 'create', 'description' => 'Create states.'],
        ['module' => 'states', 'action' => 'edit', 'description' => 'Edit states.'],
        ['module' => 'states', 'action' => 'delete', 'description' => 'Delete states.'],

        ['module' => 'operation_areas', 'action' => 'view', 'description' => 'View operation areas.'],
        ['module' => 'operation_areas', 'action' => 'create', 'description' => 'Create operation areas.'],
        ['module' => 'operation_areas', 'action' => 'edit', 'description' => 'Edit operation areas.'],
        ['module' => 'operation_areas', 'action' => 'delete', 'description' => 'Delete operation areas.'],

        ['module' => 'teams', 'action' => 'view', 'description' => 'View teams.'],
        ['module' => 'teams', 'action' => 'create', 'description' => 'Create teams.'],
        ['module' => 'teams', 'action' => 'edit', 'description' => 'Edit teams.'],
        ['module' => 'teams', 'action' => 'delete', 'description' => 'Delete teams.'],

        ['module' => 'users', 'action' => 'view', 'description' => 'View users.'],
        ['module' => 'users', 'action' => 'create', 'description' => 'Create users.'],
        ['module' => 'users', 'action' => 'edit', 'description' => 'Edit users.'],
        ['module' => 'users', 'action' => 'deactivate', 'description' => 'Deactivate users.'],
        ['module' => 'users', 'action' => 'reset_password', 'description' => 'Reset user passwords.'],

        ['module' => 'clients', 'action' => 'view', 'description' => 'View clients.'],
        ['module' => 'clients', 'action' => 'create', 'description' => 'Create clients.'],
        ['module' => 'clients', 'action' => 'edit', 'description' => 'Edit clients.'],
        ['module' => 'clients', 'action' => 'delete', 'description' => 'Delete clients.'],
        ['module' => 'clients', 'action' => 'import', 'description' => 'Import clients.'],

        ['module' => 'locations', 'action' => 'view', 'description' => 'View locations.'],
        ['module' => 'locations', 'action' => 'create', 'description' => 'Create locations.'],
        ['module' => 'locations', 'action' => 'edit', 'description' => 'Edit locations.'],
        ['module' => 'locations', 'action' => 'delete', 'description' => 'Delete locations.'],
        ['module' => 'locations', 'action' => 'import', 'description' => 'Import locations.'],

        ['module' => 'contracts', 'action' => 'view', 'description' => 'View contracts.'],
        ['module' => 'contracts', 'action' => 'create', 'description' => 'Create contracts.'],
        ['module' => 'contracts', 'action' => 'edit', 'description' => 'Edit contracts.'],
        ['module' => 'contracts', 'action' => 'delete', 'description' => 'Delete contracts.'],
        ['module' => 'contracts', 'action' => 'import', 'description' => 'Import contracts.'],

        ['module' => 'service_orders', 'action' => 'view', 'description' => 'View service orders.'],
        ['module' => 'service_orders', 'action' => 'create', 'description' => 'Create service orders.'],
        ['module' => 'service_orders', 'action' => 'edit', 'description' => 'Edit service orders.'],
        ['module' => 'service_orders', 'action' => 'delete', 'description' => 'Delete service orders.'],
        ['module' => 'service_orders', 'action' => 'dispatch', 'description' => 'Dispatch service orders.'],
        ['module' => 'service_orders', 'action' => 'import', 'description' => 'Import service orders.'],

        ['module' => 'executive_mappings', 'action' => 'view', 'description' => 'View executive mappings.'],
        ['module' => 'executive_mappings', 'action' => 'create', 'description' => 'Create executive mappings.'],
        ['module' => 'executive_mappings', 'action' => 'edit', 'description' => 'Edit executive mappings.'],
        ['module' => 'executive_mappings', 'action' => 'delete', 'description' => 'Delete executive mappings.'],

        ['module' => 'executive_replacements', 'action' => 'view', 'description' => 'View executive replacements.'],
        ['module' => 'executive_replacements', 'action' => 'create', 'description' => 'Create executive replacements.'],

        ['module' => 'dispatch_entry', 'action' => 'view', 'description' => 'View dispatch entries.'],

        ['module' => 'muster', 'action' => 'submit', 'description' => 'Submit muster receipts into workflow.'],
        ['module' => 'muster', 'action' => 'review', 'description' => 'Review muster workflow items.'],
        ['module' => 'muster', 'action' => 'approve', 'description' => 'Approve muster workflow items.'],

        ['module' => 'workflow', 'action' => 'view', 'description' => 'View workflow queues.'],
        ['module' => 'workflow', 'action' => 'final_close', 'description' => 'Perform final workflow closure.'],

        ['module' => 'reports', 'action' => 'view', 'description' => 'View reports.'],
        ['module' => 'reports', 'action' => 'export', 'description' => 'Export reports.'],

        ['module' => 'activity_logs', 'action' => 'view', 'description' => 'View audit activity logs.'],
    ],

    'role_permissions' => [
        'super_admin' => ['*'],
        'admin' => [
            'dashboard.view',
            'departments.view', 'departments.create', 'departments.edit', 'departments.delete',
            'states.view', 'states.create', 'states.edit', 'states.delete',
            'operation_areas.view', 'operation_areas.create', 'operation_areas.edit', 'operation_areas.delete',
            'teams.view', 'teams.create', 'teams.edit', 'teams.delete',
            'clients.view', 'clients.create', 'clients.edit', 'clients.delete', 'clients.import',
            'locations.view', 'locations.create', 'locations.edit', 'locations.delete', 'locations.import',
            'contracts.view', 'contracts.create', 'contracts.edit', 'contracts.delete', 'contracts.import',
            'service_orders.view', 'service_orders.create', 'service_orders.edit', 'service_orders.delete', 'service_orders.dispatch', 'service_orders.import',
            'executive_mappings.view', 'executive_mappings.create', 'executive_mappings.edit', 'executive_mappings.delete',
            'executive_replacements.view', 'executive_replacements.create',
            'dispatch_entry.view',
            'muster.submit', 'muster.review', 'muster.approve',
            'workflow.view', 'workflow.final_close',
            'reports.view', 'reports.export',
            'activity_logs.view',
        ],
        'operations' => [
            'dashboard.view',
            'clients.view',
            'locations.view',
            'contracts.view',
            'service_orders.view',
            'muster.submit',
            'workflow.view',
            'reports.view', 'reports.export',
        ],
        'reviewer' => [
            'dashboard.view',
            'clients.view',
            'locations.view',
            'contracts.view',
            'service_orders.view',
            'workflow.view',
            'muster.review', 'muster.approve',
            'reports.view', 'reports.export',
        ],
    ],

    'role_priority' => ['super_admin', 'admin', 'operations', 'reviewer', 'viewer'],

    'menu' => [
        [
            'items' => [
                ['label' => 'Dashboard', 'icon' => 'bi-grid-1x2-fill', 'route' => 'dashboard', 'permission' => 'dashboard.view', 'roles' => ['super_admin', 'admin', 'operations', 'reviewer']],
            ],
        ],
        [
            'title' => 'Master Data',
            'items' => [
                ['label' => 'Departments', 'icon' => 'bi-diagram-3-fill', 'route' => 'departments.index', 'permission' => 'departments.view', 'roles' => ['super_admin', 'admin']],
                ['label' => 'States', 'icon' => 'bi-map-fill', 'route' => 'states.index', 'permission' => 'states.view', 'roles' => ['super_admin', 'admin']],
                ['label' => 'Operation Areas', 'icon' => 'bi-bounding-box-circles', 'route' => 'operation-areas.index', 'permission' => 'operation_areas.view', 'roles' => ['super_admin', 'admin']],
                ['label' => 'Teams', 'icon' => 'bi-people-fill', 'route' => 'teams.index', 'permission' => 'teams.view', 'roles' => ['super_admin', 'admin']],
                ['label' => 'Users', 'icon' => 'bi-person-badge-fill', 'route' => 'users.index', 'permission' => 'users.view', 'roles' => ['super_admin']],
            ],
        ],
        [
            'title' => 'Client Structure',
            'items' => [
                ['label' => 'Clients', 'icon' => 'bi-buildings-fill', 'route' => 'clients.index', 'permission' => 'clients.view', 'roles' => ['super_admin', 'admin']],
                ['label' => 'Locations', 'icon' => 'bi-geo-alt-fill', 'route' => 'locations.index', 'permission' => 'locations.view', 'roles' => ['super_admin', 'admin']],
                ['label' => 'Contracts', 'icon' => 'bi-file-earmark-text-fill', 'route' => 'contracts.index', 'permission' => 'contracts.view', 'roles' => ['super_admin', 'admin']],
            ],
        ],
        [
            'title' => 'Operations',
            'items' => [
                ['label' => 'Service Orders', 'icon' => 'bi-clipboard2-check-fill', 'route' => 'service-orders.index', 'permission' => 'service_orders.view', 'roles' => ['super_admin', 'admin', 'operations']],
                ['label' => 'Dispatch Entry', 'icon' => 'bi-truck', 'route' => 'dispatch-entry.index', 'permission' => 'dispatch_entry.view', 'roles' => ['super_admin', 'admin']],
                ['label' => 'Upload Muster Roll', 'icon' => 'bi-upload', 'route' => 'bulk-receive.index', 'permission' => 'muster.submit', 'roles' => ['operations']],
                ['label' => 'Bulk Upload', 'icon' => 'bi-inboxes-fill', 'route' => 'bulk-receive.index', 'permission' => 'muster.submit', 'roles' => ['operations']],
                ['label' => 'Bulk Receive', 'icon' => 'bi-inboxes-fill', 'route' => 'bulk-receive.index', 'permission' => 'workflow.view', 'roles' => ['super_admin', 'admin', 'reviewer']],
            ],
        ],
        [
            'title' => 'Workflow',
            'items' => [
                ['label' => 'Review / Approval', 'icon' => 'bi-shield-check', 'route' => 'workflow.approvals.index', 'permission' => 'workflow.view', 'roles' => ['super_admin', 'admin', 'reviewer']],
            ],
        ],
        [
            'title' => 'Mappings',
            'items' => [
                ['label' => 'Executive Mapping', 'icon' => 'bi-diagram-2-fill', 'route' => 'executive-mappings.index', 'permission' => 'executive_mappings.view', 'roles' => ['super_admin', 'admin']],
                ['label' => 'Executive Replacement', 'icon' => 'bi-arrow-repeat', 'route' => 'executive-replacements.index', 'permission' => 'executive_replacements.view', 'roles' => ['super_admin', 'admin']],
            ],
        ],
        [
            'title' => 'Reports',
            'items' => [
                ['label' => 'Reports', 'icon' => 'bi-bar-chart-fill', 'route' => 'reports.index', 'permission' => 'reports.view', 'roles' => ['super_admin', 'admin', 'operations', 'reviewer']],
            ],
        ],
    ],

    'workflows' => [
        [
            'name' => 'Muster Submission',
            'code' => 'muster_submission',
            'description' => 'Three-step approval workflow for muster intake, review, and administrative closure.',
            'steps' => [
                ['step_order' => 1, 'role' => 'operations', 'action' => 'submit'],
                ['step_order' => 2, 'role' => 'reviewer', 'action' => 'approve'],
                ['step_order' => 3, 'role' => 'admin', 'action' => 'final_close'],
            ],
        ],
    ],
];
