@extends('layouts.app')

@section('content')
<div class="container-fluid py-4" >
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="text-center mb-5">
                    <h1 class="display-4  font-weight-bold">Profile Settings</h1>
                    <p class="lead -50">Manage your account information and preferences</p>
                </div>

                <div class="row">
                    <div class="col-md-12 mb-4">
                        <div class="card border-0 shadow-lg" style="border-radius: 15px; backdrop-filter: blur(10px); background: rgba(255, 255, 255, 0.95);">
                            <div class="card-body p-4">
                                @include('profile.partials.update-profile-information-form')
                            </div>
                        </div>
                    </div>

                    <div class="col-md-12 mb-4">
                        <div class="card border-0 shadow-lg" style="border-radius: 15px; backdrop-filter: blur(10px); background: rgba(255, 255, 255, 0.95);">
                            <div class="card-body p-4">
                                @include('profile.partials.update-password-form')
                            </div>
                        </div>
                    </div>

{{--                    <div class="col-md-12">--}}
{{--                        <div class="card border-0 shadow-lg" style="border-radius: 15px; backdrop-filter: blur(10px); background: rgba(255, 255, 255, 0.95);">--}}
{{--                            <div class="card-body p-4">--}}
{{--                                @include('profile.partials.delete-user-form')--}}
{{--                            </div>--}}
{{--                        </div>--}}
{{--                    </div>--}}
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
