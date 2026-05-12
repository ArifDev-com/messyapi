@extends('layouts.guest')

@section('content')
<div class="container-fluid min-vh-100 d-flex align-items-center" >
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="text-center mb-5">
                    <h1 class="display-4  font-weight-bold mb-3">
                        <i class="fas fa-user-plus mr-3"></i>Join Us Today
                    </h1>
                    <p class="lead -50">Create your account and start your journey</p>
                </div>

                <div class="card border-0 shadow-lg" >
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="h3 font-weight-bold text-primary">
                                <i class="fas fa-rocket mr-2"></i>{{ __('Create Account') }}
                            </h2>
                        </div>

                        <form method="POST" action="{{ route('register') }}">
                            @csrf

                            <!-- Name -->
                            <div class="form-group mb-4">
                                <label for="name" class="font-weight-bold text-dark mb-2">
                                    <i class="fas fa-user mr-2 text-primary"></i>{{ __('Full Name') }}
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" style="border-radius: 15px 0 0 15px; border: 2px solid #e9ecef; border-right: none; background: #f8f9fa;">
                                            <i class="fas fa-user text-primary"></i>
                                        </span>
                                    </div>
                                    <input id="name" type="text" class="form-control form-control-lg @error('name') is-invalid @enderror"
                                           name="name" value="{{ old('name') }}" required autofocus autocomplete="name"
                                           placeholder="Enter your full name" style="border-radius: 0 15px 15px 0; border: 2px solid #e9ecef; border-left: none;">

                                    @error('name')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Email Address -->
                            <div class="form-group mb-4">
                                <label for="email" class="font-weight-bold text-dark mb-2">
                                    <i class="fas fa-envelope mr-2 text-primary"></i>{{ __('Email Address') }}
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" style="border-radius: 15px 0 0 15px; border: 2px solid #e9ecef; border-right: none; background: #f8f9fa;">
                                            <i class="fas fa-envelope text-primary"></i>
                                        </span>
                                    </div>
                                    <input id="email" type="email" class="form-control form-control-lg @error('email') is-invalid @enderror"
                                           name="email" value="{{ old('email') }}" required autocomplete="username"
                                           placeholder="Enter your email" style="border-radius: 0 15px 15px 0; border: 2px solid #e9ecef; border-left: none;">

                                    @error('email')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Password -->
                            <div class="form-group mb-4">
                                <label for="password" class="font-weight-bold text-dark mb-2">
                                    <i class="fas fa-lock mr-2 text-primary"></i>{{ __('Password') }}
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" style="border-radius: 15px 0 0 15px; border: 2px solid #e9ecef; border-right: none; background: #f8f9fa;">
                                            <i class="fas fa-lock text-primary"></i>
                                        </span>
                                    </div>
                                    <input id="password" type="password" class="form-control form-control-lg @error('password') is-invalid @enderror"
                                           name="password" required autocomplete="new-password"
                                           placeholder="Create a strong password" style="border-radius: 0 15px 15px 0; border: 2px solid #e9ecef; border-left: none;">

                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Confirm Password -->
                            <div class="form-group mb-4">
                                <label for="password_confirmation" class="font-weight-bold text-dark mb-2">
                                    <i class="fas fa-lock mr-2 text-primary"></i>{{ __('Confirm Password') }}
                                </label>
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text" style="border-radius: 15px 0 0 15px; border: 2px solid #e9ecef; border-right: none; background: #f8f9fa;">
                                            <i class="fas fa-check-circle text-primary"></i>
                                        </span>
                                    </div>
                                    <input id="password_confirmation" type="password" class="form-control form-control-lg @error('password_confirmation') is-invalid @enderror"
                                           name="password_confirmation" required autocomplete="new-password"
                                           placeholder="Confirm your password" style="border-radius: 0 15px 15px 0; border: 2px solid #e9ecef; border-left: none;">

                                    @error('password_confirmation')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg px-5 mb-3" style="border-radius: 25px; background: linear-gradient(45deg, #f093fb, #f5576c); border: none; box-shadow: 0 4px 15px rgba(240, 147, 251, 0.4);">
                                    <i class="fas fa-user-plus mr-2"></i>{{ __('Create Account') }}
                                </button>
                            </div>
                        </form>

                        <hr class="my-4">

                        <div class="text-center">
                            <p class="text-muted mb-2">{{ __('Already have an account?') }}</p>
                            <a href="{{ route('login') }}" class="btn btn-outline-primary btn-lg px-4" style="border-radius: 25px; border: 2px solid #f093fb;">
                                <i class="fas fa-sign-in-alt mr-2"></i>{{ __('Sign In') }}
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
