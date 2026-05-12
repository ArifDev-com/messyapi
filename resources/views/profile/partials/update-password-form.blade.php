<div class="text-center mb-4">
    <h3 class="h4 font-weight-bold text-primary mb-2">
        <i class="fas fa-lock mr-2"></i>{{ __('Update Password') }}
    </h3>
    <p class="text-muted small">{{ __('Ensure your account is using a long, random password to stay secure.') }}</p>
</div>

<form method="post" action="{{ route('password.update') }}">
    @csrf
    @method('put')

    <div class="form-group">
        <label for="update_password_current_password" class="font-weight-bold text-dark">{{ __('Current Password') }}</label>
        <input id="update_password_current_password" name="current_password" type="password"
               class="form-control form-control-lg @error('current_password') is-invalid @enderror"
               autocomplete="current-password"
               style="border-radius: 10px; border: 2px solid #e9ecef;">
        @error('current_password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="update_password_password" class="font-weight-bold text-dark">{{ __('New Password') }}</label>
        <input id="update_password_password" name="password" type="password"
               class="form-control form-control-lg @error('password') is-invalid @enderror"
               autocomplete="new-password"
               style="border-radius: 10px; border: 2px solid #e9ecef;">
        @error('password')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="update_password_password_confirmation" class="font-weight-bold text-dark">{{ __('Confirm Password') }}</label>
        <input id="update_password_password_confirmation" name="password_confirmation" type="password"
               class="form-control form-control-lg @error('password_confirmation') is-invalid @enderror"
               autocomplete="new-password"
               style="border-radius: 10px; border: 2px solid #e9ecef;">
        @error('password_confirmation')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="text-center">
        <button type="submit" class="btn btn-success btn-lg px-5" style="border-radius: 25px; background: linear-gradient(45deg, #28a745, #20c997); border: none;">
            <i class="fas fa-key mr-2"></i>{{ __('Update Password') }}
        </button>

        @if (session('status') === 'password-updated')
            <div class="mt-3 alert alert-success" style="border-radius: 10px;">
                <i class="fas fa-check-circle mr-1"></i>{{ __('Password updated successfully!') }}
            </div>
        @endif
    </div>
</form>
