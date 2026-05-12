@extends('layouts.app')

@section('content')
<div class="container">
    <div class="">
        <div class="">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h4>Facebook Pages</h4>
                    <div>
                        @if(Auth::user()->facebookAccount)
                            <form method="POST" action="{{ route('facebook.disconnect') }}" class="d-inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-danger btn-sm"
                                        onclick="return confirm('Are you sure you want to disconnect your Facebook account?')">
                                    Disconnect Facebook
                                </button>
                            </form>
                        @else
                            <a href="{{ route('facebook.connect') }}" class="btn btn-primary btn-sm">
                                Connect Facebook Account
                            </a>
                        @endif
                    </div>
                </div>

                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    @if(session('error'))
                        <div class="alert alert-danger">
                            {{ session('error') }}
                        </div>
                    @endif

                    @if($pages->count() > 0)
                        <div class="row">
                            @foreach($pages as $page)
                                <div class="col-md-6 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <h5 class="card-title">{{ $page->name }}</h5>
                                            <p class="card-text">
                                                <strong>Page ID:</strong> {{ $page->page_id }}<br>
                                                @if($page->category)
                                                    <strong>Category:</strong> {{ $page->category }}<br>
                                                @endif
                                                <strong>Status:</strong>
                                                <span class="badge {{ $page->is_active ? 'badge-success' : 'badge-secondary' }}">
                                                    {{ $page->is_active ? 'Active' : 'Inactive' }}
                                                </span>
                                            </p>

                                            <div class="btn-group w-100">
                                                @if($page->webhook_data && isset($page->webhook_data['subscribed']) && $page->webhook_data['subscribed'])
                                                    <button class="btn btn-success btn-sm" disabled>
                                                        <i class="fas fa-check"></i> Webhook Active
                                                    </button>
                                                @else
                                                    <form method="POST" action="{{ route('facebook.webhook.setup', $page) }}" class="d-inline">
                                                        @csrf
                                                        <button type="submit" class="btn btn-outline-primary btn-sm">
                                                            <i class="fas fa-plug"></i> Setup Webhook
                                                        </button>
                                                    </form>
                                                @endif

                                                <a href="#" class="btn btn-outline-info btn-sm" onclick="showMessages({{ $page->id }}, '{{ $page->name }}')">
                                                    <i class="fas fa-comments"></i> View Messages
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-5">
                            <i class="fab fa-facebook-square fa-4x text-muted mb-3"></i>
                            <h5>No Facebook Pages Connected</h5>
                            <p class="text-muted">Connect your Facebook account to manage your pages and receive messages.</p>
                            @if(!Auth::user()->facebookAccount)
                                <a href="{{ route('facebook.connect') }}" class="btn btn-primary">
                                    Connect Facebook Account
                                </a>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Messages Modal -->
<div class="modal fade" id="messagesModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Messages for <span id="pageName"></span></h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="messagesContent">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="sr-only">Loading...</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function showMessages(pageId, pageName) {
    $('#pageName').text(pageName);
    $('#messagesModal').modal('show');

    // Load messages via AJAX (you'll need to implement this endpoint)
    $('#messagesContent').html('<div class="text-center"><div class="spinner-border" role="status"><span class="sr-only">Loading...</span></div></div>');
}
</script>
@endsection
