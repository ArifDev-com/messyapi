@extends('layouts.app')

@section('content')
<div class="container">
    <div>
        <div>
            <div class="card">
                <div class="card-header">{{ __('Dashboard') }}</div>
                <div class="card-body">
                    <div class="">
                        <h5>Actions</h5>
                        <div class="row">
                            @if(!Auth::user()->facebookAccount)
                            <div class="col-md-6">
                                <a href="{{ route('facebook.connect') }}" class="btn btn-primary btn-block">
                                    <i class="fab fa-facebook-messenger"></i> Connect Facebook
                                </a>
                            </div>
                            @else
                            <div class="col-md-6">
                                <a href="{{ route('facebook.pages') }}" class="btn btn-primary btn-block">
                                    <i class="fab fa-facebook-messenger"></i> Manage Facebook Messenger
                                </a>
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
