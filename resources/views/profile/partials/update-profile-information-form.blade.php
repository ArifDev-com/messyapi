<div class="text-center mb-4">
    <h3 class="h4 font-weight-bold text-primary mb-2">
        <i class="fas fa-user-circle mr-2"></i>{{ __('Profile Information') }}
    </h3>
    <p class="text-muted small">{{ __("Update your account's profile information and email address.") }}</p>
</div>

<form id="send-verification" method="post" action="{{ route('verification.send') }}">
    @csrf
</form>

<form method="post" action="{{ route('profile.update') }}">
    @csrf
    @method('patch')

    <div class="form-group">
        <label for="name" class="font-weight-bold text-dark">{{ __('Name') }}</label>
        <input id="name" name="name" type="text" class="form-control form-control-lg @error('name') is-invalid @enderror"
               value="{{ old('name', $user->name) }}" required autofocus autocomplete="name"
               style="border-radius: 10px; border: 2px solid #e9ecef;">
        @error('name')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror
    </div>

    <div class="form-group">
        <label for="email" class="font-weight-bold text-dark">{{ __('Email') }}</label>
        <input id="email" name="email" type="email" class="form-control form-control-lg @error('email') is-invalid @enderror"
               value="{{ old('email', $user->email) }}" required autocomplete="username"
               style="border-radius: 10px; border: 2px solid #e9ecef;">
        @error('email')
            <div class="invalid-feedback">{{ $message }}</div>
        @enderror

        @if ($user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! $user->hasVerifiedEmail())
            <div class="mt-3 p-3 bg-light rounded" style="border-radius: 10px;">
                <p class="text-warning mb-2">
                    <i class="fas fa-exclamation-triangle mr-1"></i>{{ __('Your email address is unverified.') }}
                </p>
                <button form="send-verification" class="btn btn-outline-primary btn-sm">
                    {{ __('Click here to re-send the verification email.') }}
                </button>

                @if (session('status') === 'verification-link-sent')
                    <p class="mt-2 text-success font-weight-bold">
                        <i class="fas fa-check-circle mr-1"></i>{{ __('A new verification link has been sent to your email address.') }}
                    </p>
                @endif
            </div>
        @endif
    </div>

    <div class="text-center">
        <button type="submit" class="btn btn-primary btn-lg px-5" style="border-radius: 25px; background: linear-gradient(45deg, #667eea, #764ba2); border: none;">
            <i class="fas fa-save mr-2"></i>{{ __('Save Changes') }}
        </button>

        @if (session('status') === 'profile-updated')
            <div class="mt-3 alert alert-success" style="border-radius: 10px;">
                <i class="fas fa-check-circle mr-1"></i>{{ __('Profile updated successfully!') }}
            </div>
        @endif
    </div>
</form>
