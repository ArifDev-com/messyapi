@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">Settings</h1>
        </div>
    </div>

    <!-- API Configurations -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>API Configurations</h5>
                </div>
                <div class="card-body">
                    @if(session('success'))
                        <div class="alert alert-success">
                            {{ session('success') }}
                        </div>
                    @endif

                    <form method="POST" action="{{ route('settings.update') }}">
                        @csrf

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="openai_api_url">AI Vendor API URL</label>
                                    <input type="text" class="form-control" id="openai_api_url" name="openai_api_url"
                                           value="{{ old('openai_api_url', $settings->where('key', 'openai_api_url')->first()?->value) }}"
                                           placeholder="https://api.openrouter.com/v1">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="openai_api_key">AI Vendor API Key</label>
                                    <input type="text" class="form-control" id="openai_api_key" name="openai_api_key"
                                           value="{{ old('openai_api_key', $settings->where('key', 'openai_api_key')->first()?->value) }}"
                                           placeholder="sk-...">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="openai_api_model">Model</label>
                                    <input type="text" class="form-control" id="openai_api_model" name="openai_api_model"
                                           value="{{ old('openai_api_model', $settings->where('key', 'openai_api_model')->first()?->value) }}"
                                           placeholder="deepseek">
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="whatsapp_api_key">WhatsApp API Key</label>
                                    <input type="text" class="form-control" id="whatsapp_api_key" name="whatsapp_api_key"
                                           value="{{ old('whatsapp_api_key', Auth::user()->whatsapp_api_key) }}"
                                           placeholder="Your WhatsApp API Key">
                                    <small class="form-text text-muted">Required for WhatsApp integration</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="whatsapp_phone_number_id">WhatsApp Phone Number ID</label>
                                    <input type="text" class="form-control" id="whatsapp_phone_number_id" name="whatsapp_phone_number_id"
                                           value="{{ old('whatsapp_phone_number_id', Auth::user()->whatsapp_phone_number_id) }}"
                                           placeholder="Your WhatsApp Phone Number ID">
                                    <small class="form-text text-muted">Required for WhatsApp messaging</small>
                                </div>
                            </div>
                        </div>

                        <button type="submit" class="btn btn-primary">Save Settings</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Facebook Pages Management -->
    <div class="row">
        <div class="col-12">
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

                                            <!-- Auto Reply Toggle -->
                                            <div class="form-check mb-3">
                                                <input class="form-check-input auto-reply-toggle" type="checkbox"
                                                       id="auto_reply_{{ $page->id }}"
                                                       {{ $page->auto_reply_enabled ? 'checked' : '' }}
                                                       data-page-id="{{ $page->id }}">
                                                <label class="form-check-label" for="auto_reply_{{ $page->id }}">
                                                    Enable Auto Reply
                                                </label>
                                            </div>

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

// Handle auto reply toggle
$(document).on('change', '.auto-reply-toggle', function() {
    const pageId = $(this).data('page-id');
    const enabled = $(this).is(':checked');

    $.ajax({
        url: `/settings/pages/${pageId}/toggle-auto-reply`,
        method: 'POST',
        data: {
            auto_reply_enabled: enabled,
            _token: '{{ csrf_token() }}'
        },
        success: function(response) {
            // Optional: Show success message
        },
        error: function() {
            // Revert the toggle on error
            $(this).prop('checked', !enabled);
        }
    });
});
</script>
@endsection
