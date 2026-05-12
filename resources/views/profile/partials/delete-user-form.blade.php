<div class="text-center mb-4">
    <h3 class="h4 font-weight-bold text-danger mb-2">
        <i class="fas fa-exclamation-triangle mr-2"></i>{{ __('Delete Account') }}
    </h3>
    <p class="text-muted small">{{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}</p>
</div>

<button type="button" class="btn btn-danger btn-lg px-5" data-toggle="modal" data-target="#deleteAccountModal" style="border-radius: 25px;">
    <i class="fas fa-trash-alt mr-2"></i>{{ __('Delete Account') }}
</button>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1" role="dialog" aria-labelledby="deleteAccountModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content" style="border-radius: 15px; border: none; box-shadow: 0 10px 30px rgba(0,0,0,0.3);">
            <div class="modal-header bg-danger text-white" style="border-radius: 15px 15px 0 0; border: none;">
                <h5 class="modal-title" id="deleteAccountModalLabel">
                    <i class="fas fa-exclamation-triangle mr-2"></i>{{ __('Delete Account') }}
                </h5>
                <button type="button" class="close text-white" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form method="post" action="{{ route('profile.destroy') }}">
                @csrf
                @method('delete')

                <div class="modal-body p-4">
                    <div class="text-center mb-4">
                        <i class="fas fa-user-times fa-3x text-danger mb-3"></i>
                        <h5 class="font-weight-bold">{{ __('Are you sure you want to delete your account?') }}</h5>
                        <p class="text-muted">{{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}</p>
                    </div>

                    <div class="form-group">
                        <label for="delete_password" class="font-weight-bold">{{ __('Password') }}</label>
                        <input id="delete_password" name="password" type="password"
                               class="form-control form-control-lg @error('password') is-invalid @enderror"
                               placeholder="{{ __('Enter your password') }}" required
                               style="border-radius: 10px; border: 2px solid #e9ecef;">
                        @error('password')
                            <div class="invalid-feedback">{{ $message }}</div>
                        @enderror
                    </div>
                </div>

                <div class="modal-footer" style="border-radius: 0 0 15px 15px; border: none; background: #f8f9fa;">
                    <button type="button" class="btn btn-secondary btn-lg" data-dismiss="modal" style="border-radius: 25px;">
                        <i class="fas fa-times mr-1"></i>{{ __('Cancel') }}
                    </button>
                    <button type="submit" class="btn btn-danger btn-lg" style="border-radius: 25px;">
                        <i class="fas fa-trash-alt mr-1"></i>{{ __('Delete Account') }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
