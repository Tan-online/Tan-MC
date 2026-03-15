<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>Login | Tan-MC</title>

        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=figtree:400,500,600,700&display=swap" rel="stylesheet" />
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

        <style>
            :root {
                --tmc-primary: #0f5c4d;
                --tmc-primary-dark: #0b4a3e;
                --tmc-accent: #f59e0b;
                --tmc-surface: #ffffff;
                --tmc-surface-soft: rgba(255, 255, 255, 0.74);
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
                max-width: 34rem;
            }

            .brand-logo {
                width: min(100%, 320px);
                max-width: 320px;
                object-fit: contain;
                filter: drop-shadow(0 24px 34px rgba(0, 0, 0, 0.28));
            }

            .brand-description {
                max-width: 22rem;
                margin: 0 auto;
                font-size: 1.1rem;
                line-height: 1.75;
                color: rgba(255, 255, 255, 0.82);
            }

            .form-panel {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 2rem 1.25rem;
            }

            .form-card {
                width: 100%;
                max-width: 420px;
                padding: 2.4rem 2rem;
                border: 1px solid rgba(216, 226, 234, 0.92);
                border-radius: 1.75rem;
                background: var(--tmc-surface);
                box-shadow: var(--tmc-shadow);
            }

            .form-logo {
                width: min(100%, 180px);
                max-width: 180px;
                object-fit: contain;
            }

            .form-label {
                font-size: 0.95rem;
                color: var(--tmc-text);
            }

            .form-control,
            .form-check-input {
                border-color: var(--tmc-border);
            }

            .form-control {
                min-height: 3.25rem;
                border-radius: 0.95rem;
                padding-inline: 0.95rem;
            }

            .form-control::placeholder {
                color: #9aa9b7;
            }

            .form-control:focus,
            .form-check-input:focus {
                border-color: rgba(15, 92, 77, 0.45);
                box-shadow: 0 0 0 0.25rem rgba(15, 92, 77, 0.12);
            }

            .form-check-label {
                color: var(--tmc-muted);
            }

            .btn-primary {
                --bs-btn-bg: var(--tmc-primary);
                --bs-btn-border-color: var(--tmc-primary);
                --bs-btn-hover-bg: var(--tmc-primary-dark);
                --bs-btn-hover-border-color: var(--tmc-primary-dark);
                --bs-btn-active-bg: var(--tmc-primary-dark);
                --bs-btn-active-border-color: var(--tmc-primary-dark);
                min-height: 3.25rem;
                border-radius: 0.95rem;
                font-weight: 600;
                letter-spacing: 0.01em;
            }

            .alert {
                border: 0;
                border-radius: 1rem;
            }

            @media (max-width: 991.98px) {
                .brand-panel {
                    min-height: auto;
                }

                .brand-panel::before {
                    width: 22rem;
                    height: 22rem;
                    box-shadow: 0 0 0 34px rgba(255, 255, 255, 0.04);
                    transform: translate(-50%, -54%);
                }

                .brand-content {
                    padding-top: 3rem;
                    padding-bottom: 3rem;
                }

                .brand-logo {
                    max-width: 240px;
                }

                .brand-description {
                    font-size: 1rem;
                    line-height: 1.7;
                }
            }

            @media (max-width: 575.98px) {
                body,
                .auth-shell {
                    height: auto;
                    min-height: 100vh;
                    overflow: auto;
                }

                .form-card {
                    padding: 1.75rem 1.25rem;
                    border-radius: 1.35rem;
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
                            Enterprise platform for managing<br>
                            muster roll submissions, tracking<br>
                            and monitoring.
                        </p>
                    </div>
                </div>

                <div class="col-lg-6 form-panel">
                    <div class="form-card">
                        <div class="text-center mb-4 pb-2">
                            <img src="{{ asset('assets/logo2.png') }}" alt="S&IB company logo" class="form-logo">
                        </div>

                        @if (session('status'))
                            <div class="alert alert-success">{{ session('status') }}</div>
                        @endif

                        @if ($errors->any())
                            <div class="alert alert-danger">
                                {{ $errors->first('employee_code') ?: $errors->first('password') ?: $errors->first() }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login') }}">
                            @csrf

                            <div class="mb-3">
                                <label for="employee_code" class="form-label fw-semibold">Employee Code / Login ID</label>
                                <input
                                    id="employee_code"
                                    type="text"
                                    name="employee_code"
                                    value="{{ old('employee_code') }}"
                                    class="form-control @error('employee_code') is-invalid @enderror"
                                    placeholder="Enter 6-digit employee code"
                                    required
                                    autofocus
                                    autocomplete="username"
                                >
                                @error('employee_code')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="mb-3">
                                <label for="password" class="form-label fw-semibold">Password</label>
                                <input
                                    id="password"
                                    type="password"
                                    name="password"
                                    class="form-control @error('password') is-invalid @enderror"
                                    placeholder="Enter password"
                                    required
                                    autocomplete="current-password"
                                >
                                @error('password')
                                    <div class="invalid-feedback">{{ $message }}</div>
                                @enderror
                            </div>

                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" value="1" id="remember" name="remember">
                                <label class="form-check-label" for="remember">
                                    Remember Me
                                </label>
                            </div>

                            <button type="submit" class="btn btn-primary w-100">
                                Login
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </body>
</html>
