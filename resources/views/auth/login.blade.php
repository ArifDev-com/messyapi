@extends('layouts.guest')

@section('content')
<div class="container-fluid min-vh-100 d-flex align-items-center">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-6 col-md-8">
                <div class="text-center mb-5">
                    <h1 class="display-4  font-weight-bold mb-3">
                        {{config('app.name')}}
                    </h1>
                    <p class="lead -50">Sign in to your account to continue</p>
                </div>

                <div class="card border-0 shadow-lg" style="border-radius: 20px; backdrop-filter: blur(15px); background: rgba(255, 255, 255, 0.95);">
                    <div class="card-body p-5">
                        <div class="text-center mb-4">
                            <h2 class="h3 font-weight-bold text-primary">
                                <i class="fas fa-user-circle mr-2"></i>{{ __('Sign In') }}
                            </h2>
                        </div>

                        @if (session('status'))
                            <div class="alert alert-success" role="alert" style="border-radius: 10px;">
                                <i class="fas fa-check-circle mr-2"></i>{{ session('status') }}
                            </div>
                        @endif

                        <form method="POST" action="{{ route('login') }}">
                            @csrf

                            <!-- Email Address -->
                            <div class="form-group mb-4">
                                <label for="email" class="font-weight-bold text-dark mb-2">
                                    <i class="fas fa-envelope mr-2 text-primary"></i>{{ __('Email Address') }}
                                </label>
                                <div class="">
                                    <input id="email" type="email" class="form-control @error('email') is-invalid @enderror"
                                           name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                                           placeholder="Enter your email" >

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
                                <div class="">
                                    <input id="password" type="password" class="form-control @error('password') is-invalid @enderror"
                                           name="password" required autocomplete="current-password"
                                           placeholder="Enter your password" >

                                    @error('password')
                                        <div class="invalid-feedback">{{ $message }}</div>
                                    @enderror
                                </div>
                            </div>

                            <!-- Remember Me -->
                            <div class="form-check mb-4">
                                <input class="form-check-input" type="checkbox" name="remember" id="remember"
                                       style="border-radius: 5px; border: 2px solid #667eea;" {{ old('remember') ? 'checked' : '' }}>
                                <label class="form-check-label font-weight-bold text-dark" for="remember">
                                    {{ __('Remember me') }}
                                </label>
                            </div>

                            <div class="text-center">
                                <button type="submit" class="btn btn-primary btn-lg px-5 mb-3" style="border-radius: 25px; background: linear-gradient(45deg, #667eea, #764ba2); border: none; box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);">
                                    <i class="fas fa-sign-in-alt mr-2"></i>{{ __('Sign In') }}
                                </button>

                                <div class="mt-3">
                                    @if (Route::has('password.request'))
                                        <a class="text-primary font-weight-bold" href="{{ route('password.request') }}" style="text-decoration: none;">
                                            <i class="fas fa-key mr-1"></i>{{ __('Forgot your password?') }}
                                        </a>
                                    @endif
                                </div>
                            </div>
                        </form>

{{--                        <hr class="my-4">--}}

{{--                        <div class="text-center">--}}
{{--                            <p class="text-muted mb-2">{{ __('Don\'t have an account?') }}</p>--}}
{{--                            <a href="{{ route('register') }}" class="btn btn-outline-primary btn-lg px-4" style="border-radius: 25px; border: 2px solid #667eea;">--}}
{{--                                <i class="fas fa-user-plus mr-2"></i>{{ __('Create Account') }}--}}
{{--                            </a>--}}
{{--                        </div>--}}
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
