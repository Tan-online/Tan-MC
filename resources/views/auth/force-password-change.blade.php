@extends('layouts.app')

@section('title', 'Change Password | Tan-MC')

@section('content')
    <div class="row justify-content-center py-4">
        <div class="col-12 col-lg-7 col-xl-5">
            <div class="surface-card p-4 p-md-5">
                <div class="mb-4">
                    <span class="badge text-bg-warning-subtle text-warning border border-warning-subtle mb-3">Action Required</span>
                    <h1 class="h3 fw-bold mb-2">Change your password</h1>
                    <p class="text-muted mb-0">
                        Your account was created or reset with a temporary password. Set a new password before accessing the ERP.
                    </p>
                </div>

                <div class="bg-light border rounded-3 p-3 mb-4 small">
                    <div><span class="text-muted">User:</span> {{ $user->name }}</div>
                    <div><span class="text-muted">Employee Code:</span> {{ $user->employee_code }}</div>
                    <div><span class="text-muted">Email:</span> {{ $user->email }}</div>
                </div>

                <form method="POST" action="{{ route('password.force.update') }}" class="d-grid gap-3">
                    @csrf
                    @method('PUT')

                    <div>
                        <label for="password" class="form-label">New Password</label>
                        <input id="password" name="password" type="password" class="form-control @error('password') is-invalid @enderror" required autocomplete="new-password">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>

                    <div>
                        <label for="password_confirmation" class="form-label">Confirm Password</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" class="form-control" required autocomplete="new-password">
                    </div>

                    <div class="d-flex flex-column flex-sm-row align-items-sm-center justify-content-between gap-3 pt-2">
                        <a href="{{ route('dashboard') }}" class="btn btn-light border disabled" aria-disabled="true">Restricted Until Password Change</a>
                        <button type="submit" class="btn btn-primary">Update Password</button>
                    </div>
                </form>

                <form method="POST" action="{{ route('logout') }}" class="pt-3">
                    @csrf
                    <button type="submit" class="btn btn-link px-0 text-decoration-none">Sign out instead</button>
                </form>
            </div>
        </div>
    </div>
@endsection