<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Change Password | Tan-MC</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

        <style>
            :root {
                --tmc-primary: #0f5c4d;
                --tmc-primary-dark: #0b4a3e;
                --tmc-accent: #f59e0b;
                --tmc-surface: #ffffff;
                --tmc-border: #d8e2ea;
                --tmc-text: #163047;
                --tmc-muted: #5f7184;
                --tmc-shadow: 0 28px 60px rgba(22, 48, 71, 0.15);
                --tmc-background: #edf3f7;
            }

            body {
                height: 100vh;
                margin: 0;
                overflow: hidden;
                font-family: 'Figtree', sans-serif;
                background:
                    radial-gradient(circle at top left, rgba(245, 158, 11, 0.12), transparent 24%),
                    radial-gradient(circle at bottom right, rgba(15, 92, 77, 0.1), transparent 28%),
                    linear-gradient(135deg, #f8fbfd 0%, var(--tmc-background) 100%);
                color: var(--tmc-text);
            }

            .auth-shell {
                height: 100vh;
                overflow: hidden;
            }

            .brand-panel {
                position: relative;
                overflow: hidden;
                background:
                    radial-gradient(circle at top left, rgba(255, 255, 255, 0.18), transparent 26%),
                    linear-gradient(160deg, #15364d 0%, #1e5164 45%, #0f5c4d 100%);
                color: #fff;
                isolation: isolate;
            }

            .brand-panel::before,
            .brand-panel::after {
                content: "";
                position: absolute;
                border-radius: 50%;
                z-index: -1;
            }

            .brand-panel::before {
                width: 32rem;
                height: 32rem;
                background: rgba(255, 255, 255, 0.08);
                top: 50%;
                left: 50%;
                transform: translate(-50%, -58%);
                box-shadow: 0 0 0 55px rgba(255, 255, 255, 0.04);
            }

            .brand-panel::after {
                width: 14rem;
                height: 14rem;
                background: rgba(245, 158, 11, 0.14);
                bottom: -4rem;
                right: -3rem;
            }

            .brand-content {
                max-width: 30rem;
            }

            .brand-logo {
                width: min(100%, 240px);
                max-width: 240px;
                object-fit: contain;
                filter: drop-shadow(0 24px 34px rgba(0, 0, 0, 0.28));
            }

            .brand-description {
                max-width: 21rem;
                margin: 0 auto;
                font-size: 0.98rem;
                line-height: 1.65;
                color: rgba(255, 255, 255, 0.82);
            }

            .form-panel {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 1.5rem 1.25rem;
            }

            .form-card {
                width: 100%;
                max-width: 430px;
                padding: 1.9rem 1.75rem;
                border: 1px solid rgba(216, 226, 234, 0.92);
                border-radius: 1.75rem;
                background: var(--tmc-surface);
                box-shadow: var(--tmc-shadow);
            }

            .form-logo {
                width: min(100%, 165px);
                max-width: 165px;
                object-fit: contain;
            }

            .form-label {
                font-size: 0.95rem;
                color: var(--tmc-text);
            }

            .form-control {
                min-height: 3rem;
                border-color: var(--tmc-border);
                border-radius: 0.95rem;
                padding-inline: 0.95rem;
            }

            .form-control:focus {
                border-color: rgba(15, 92, 77, 0.45);
                box-shadow: 0 0 0 0.25rem rgba(15, 92, 77, 0.12);
            }

            .identity-card {
                border: 1px solid var(--tmc-border);
                border-radius: 1rem;
                background: #f7fafc;
                padding: 1rem;
            }

            .btn-primary {
                --bs-btn-bg: var(--tmc-primary);
                --bs-btn-border-color: var(--tmc-primary);
                --bs-btn-hover-bg: var(--tmc-primary-dark);
                --bs-btn-hover-border-color: var(--tmc-primary-dark);
                --bs-btn-active-bg: var(--tmc-primary-dark);
                --bs-btn-active-border-color: var(--tmc-primary-dark);
                min-height: 3rem;
                border-radius: 0.95rem;
                font-weight: 600;
            }

            .btn-outline-secondary {
                min-height: 3rem;
                border-radius: 0.95rem;
            }

            .alert {
                border: 0;
                border-radius: 1rem;
            }

            @media (max-width: 991.98px) {
                .brand-content {
                    padding-top: 2rem;
                    padding-bottom: 2rem;
                }

                .brand-logo {
                    max-width: 210px;
                }
            }

            @media (max-width: 575.98px) {
                .form-card {
                    padding: 1.5rem 1.1rem;
                    border-radius: 1.35rem;
                }

                .brand-description {
                    font-size: 0.92rem;
                }
            }
        </style>
    </head>
    <body>
        <div class="container-fluid auth-shell">
            <div class="row min-vh-100">
                <div class="col-lg-6 brand-panel d-flex align-items-center justify-content-center">
                    <div class="brand-content text-center px-4 py-5">
                        <img src="{{ asset('assets/logo.png') }}" alt="Tan-MC mascot logo" class="brand-logo mb-4">
                        <p class="brand-description mb-0">
                            Secure account activation for your Tan-MC ERP workspace.
                        </p>
                    </div>
                </div>

                <div class="col-lg-6 form-panel">
                    <div class="form-card">
                        <div class="text-center mb-3 pb-1">
                            <img src="{{ asset('assets/logo2.png') }}" alt="S&IB company logo" class="form-logo mb-2">
                        </div>

                        <div class="mb-3">
                            <h1 class="h3 fw-bold mb-2">Change your password</h1>
                            <p class="text-muted mb-0">
                                Set your new password to continue to the ERP dashboard.
                            </p>
                        </div>

                        @if ($errors->any())
                            <div class="alert alert-danger py-2 px-3 mb-3">{{ $errors->first('password') ?: $errors->first() }}</div>
                        @endif

                        <div class="identity-card mb-3 small">
                            <div><span class="text-muted">User:</span> {{ $user->name }}</div>
                            <div><span class="text-muted">Employee Code:</span> {{ $user->employee_code }}</div>
                            <div><span class="text-muted">Email:</span> {{ $user->email }}</div>
                        </div>

                        <form method="POST" action="{{ route('password.force.update') }}" class="d-grid gap-2" autocomplete="off">
                            @csrf
                            @method('PUT')

                            <div>
                                <label for="password" class="form-label fw-semibold">New Password</label>
                                <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" required autofocus autocomplete="new-password">
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div>
                                <label for="password_confirmation" class="form-label fw-semibold">Confirm Password</label>
                                <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required autocomplete="new-password">
                            </div>

                            <div class="d-grid gap-2 pt-1">
                                <button type="submit" class="btn btn-primary">Save New Password</button>
                            </div>
                        </form>

                        <form method="POST" action="{{ route('logout') }}" class="pt-2">
                            @csrf
                            <button type="submit" class="btn btn-link px-0 text-decoration-none">Sign out instead</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>