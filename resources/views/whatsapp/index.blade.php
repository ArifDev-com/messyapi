@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <h2>Whatsapp Messages</h2>
    </div>
    @php
    try{
        $check = app(\Kstmostofa\LaravelWhatsApp\Web\WebClient::class)->session('main')->chats();
    } catch (\Throwable $th) {
        $check = null;
    }
     @endphp
    @if(!$check)
        <div class="alert alert-danger">
            Whatsapp connection is missing. <a href="{{ url('whatsapp/sessions') }}">Please connect</a>
        </div>
    @endif

    <iframe src="/whatsapp/chats" style="
        width: 100%;
        height: 90vh;
        border: none;
    "></iframe>
@endsection
