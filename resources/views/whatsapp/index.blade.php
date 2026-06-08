@extends('layouts.app')

@section('content')
    <div class="container-fluid">
        <h2>Whatsapp Messages</h2>
        <div class="form-check mr-3 float-right d-inline-block">
            <input class="form-check-input" type="checkbox" id="customerAutoReplyToggle"  @if(!\App\Models\Setting::where('key', 'disable_whatsapp')->exists()) checked @endif
            onchange="updateCustomerAutoReplyStatus('0', this)"
            >
            <label class="form-check-label" for="customerAutoReplyToggle">
                WhatsApp Auto Reply Enabled
            </label>
        </div>
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

    <script>
        function updateCustomerAutoReplyStatus (selectedUserId, input) {
            const enabled = input.checked;
            const url = `/messenger-whatsapp/customer/toggle-auto-reply/${selectedUserId}`;

            fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify({
                    auto_reply_enabled: enabled
                })
            })
                .then(response => {
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    return response.json();
                })
                .then(data => {
                    // Optional: Show success message
                    console.log('Auto reply updated successfully:', data);
                    // You can add a toast notification here if needed
                })
                .catch(error => {
                    // Revert the toggle on error
                    if (inputElement) {
                        inputElement.checked = !enabled;
                    }
                    alert('Failed to update auto reply setting');
                    console.error('Error:', error);
                });
        }
    </script>
@endsection
