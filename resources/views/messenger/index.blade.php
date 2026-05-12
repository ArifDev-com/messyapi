@extends('layouts.app')

@section('content')
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <h3 class="mb-4">Messenger</h3>
        </div>
    </div>

    <div class="row">
        <!-- Customer List Sidebar -->
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h5>Customers</h5>
                    <input type="text" class="form-control mt-2" id="customerSearch" placeholder="Search customers...">
                </div>
                <div class="card-body p-0" style="max-height: 600px; overflow-y: auto;">
                    <div id="customersList">
                        @foreach($customers as $customer)
                            <div class="customer-item p-3 border-bottom" data-customer-id="{{ $customer->id }}" style="cursor: pointer;">
                                <div class="d-flex align-items-center">
                                    <div class="flex-grow-1">
                                        <strong>{{ $customer->name ?? 'Unknown Customer' }}</strong>
                                        <br>
                                        <small class="text-muted">
                                            {{ $customer->messages->first()?->message_text ? Str::limit($customer->messages->first()->message_text, 30) : 'No messages yet' }}
                                        </small>
                                        <br>
                                        <small class="text-muted">
                                            {{ $customer->last_message_at ? $customer->last_message_at->diffForHumans() : '' }}
                                        </small>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>

        <!-- Chat Interface -->
        <div class="col-md-8">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 id="chatTitle">Select a customer to start chatting</h5>
                    <div id="autoReplyStatus" class="">
                        <div class="d-flex align-items-center">
                            <div class="form-check mr-3">
                                <input class="form-check-input" type="checkbox" id="customerAutoReplyToggle">
                                <label class="form-check-label" for="customerAutoReplyToggle">
                                    Auto Reply
                                </label>
                            </div>
                            <span class="badge badge-success d-none" id="pageAutoReplyBadge">Page: ON</span>
                        </div>
                    </div>
                </div>
                <div class="card-body" id="chatContainer" style="height: 400px; overflow-y: auto; display: none;">
                    <div id="messagesContainer">
                        <!-- Messages will be loaded here -->
                    </div>
                </div>
                <div class="card-footer" id="messageInput" style="display: none;">
                    <div class="mb-2">
                        <input type="file" class="form-control-file" id="imageFiles" multiple accept="image/*" style="display: none;">
                        <button class="btn btn-outline-secondary btn-sm" type="button" id="attachImageBtn">
                            <i class="fas fa-image"></i> Attach Images
                        </button>
                        <small id="fileCount" class="text-muted ml-2"></small>
                    </div>
                    <div class="input-group">
                        <input type="text" class="form-control" id="messageText" placeholder="Type your message..." maxlength="1000">
                        <div class="input-group-append">
                            <button class="btn btn-primary" type="button" id="sendMessage">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let selectedCustomerId = null;
let selectedCustomerName = null;
let messagePingInterval = null;
let lastMessageTimestamp = null;
let isCheckingForNewMessages = false;

function playNewMessageSound() {
    const audioContext = new (window.AudioContext || window.webkitAudioContext)();
    const oscillator = audioContext.createOscillator();
    const gainNode = audioContext.createGain();

    oscillator.connect(gainNode);
    gainNode.connect(audioContext.destination);

    oscillator.frequency.setValueAtTime(800, audioContext.currentTime);
    oscillator.type = 'sine';
    gainNode.gain.setValueAtTime(0.3, audioContext.currentTime);

    oscillator.start(audioContext.currentTime);
    oscillator.stop(audioContext.currentTime + 0.2);
}

$(document).ready(function() {
    // Customer search functionality
    $('#customerSearch').on('input', function() {
        const searchTerm = $(this).val().toLowerCase();
        $('.customer-item').each(function() {
            const customerName = $(this).find('strong').text().toLowerCase();
            const messageText = $(this).find('small').first().text().toLowerCase();
            if (customerName.includes(searchTerm) || messageText.includes(searchTerm)) {
                $(this).show();
            } else {
                $(this).hide();
            }
        });
    });

    // Customer selection
    $(document).on('click', '.customer-item', function() {
        $('.customer-item').removeClass('bg-light');
        $(this).addClass('bg-light');

        // Clear existing ping interval
        if (messagePingInterval) {
            clearInterval(messagePingInterval);
            messagePingInterval = null;
        }

        selectedCustomerId = $(this).data('customer-id');
        selectedCustomerName = $(this).find('strong').text();

        $('#chatTitle').text('Chat with ' + selectedCustomerName);
        $('#chatContainer').show();
        $('#messageInput').show();
        $('#autoReplyStatus').show();

        loadMessages();
        loadCustomerAutoReplyStatus();

        // Start ping interval for new messages
        startMessagePing();
    });

    // Attach image button
    $('#attachImageBtn').on('click', function() {
        $('#imageFiles').click();
    });

    // Handle file selection
    $('#imageFiles').on('change', function() {
        const files = this.files;
        const count = files.length;
        if (count > 0) {
            $('#fileCount').text(`${count} image${count > 1 ? 's' : ''} selected`);
        } else {
            $('#fileCount').text('');
        }
    });

    // Send message
    $('#sendMessage').on('click', function() {
        sendMessage();
    });

    $('#messageText').on('keypress', function(e) {
        if (e.which === 13) { // Enter key
            sendMessage();
        }
    });

    function sendMessage() {
        const message = $('#messageText').val().trim();
        const files = $('#imageFiles')[0].files;

        if ((!message && files.length === 0) || !selectedCustomerId) return;

        $('#sendMessage').prop('disabled', true).html('<i class="fas fa-spinner fa-spin"></i> Sending...');

        const formData = new FormData();
        formData.append('message', message);
        formData.append('_token', '{{ csrf_token() }}');

        // Add files to form data
        for (let i = 0; i < files.length; i++) {
            formData.append('images[]', files[i]);
        }

        $.ajax({
            url: `/messenger/customer/${selectedCustomerId}/send-message`,
            method: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            success: function(response) {
                $('#messageText').val('');
                $('#imageFiles').val('');
                $('#fileCount').text('');
                loadMessages();
            },
            error: function(xhr) {
                alert('Failed to send message: ' + xhr.responseJSON?.error || 'Unknown error');
            },
            complete: function() {
                $('#sendMessage').prop('disabled', false).html('<i class="fas fa-paper-plane"></i> Send');
            }
        });
    }

    function loadMessages() {
        if (!selectedCustomerId) return;

        $.ajax({
            url: `/messenger/customer/${selectedCustomerId}/messages`,
            method: 'GET',
            success: function(messages) {
                renderMessages(messages);

                // Update last message timestamp for ping functionality
                if (messages.length > 0) {
                    lastMessageTimestamp = messages[messages.length - 1].sent_at;
                }
            },
            error: function(xhr) {
                $('#messagesContainer').html('<div class="alert alert-danger">Failed to load messages</div>');
            }
        });
    }

    function startMessagePing() {
        if (!selectedCustomerId) return;

        messagePingInterval = setInterval(function() {
            checkForNewMessages();
        }, 5000); // 5 seconds
    }

    function checkForNewMessages() {
        if (!selectedCustomerId || !lastMessageTimestamp || isCheckingForNewMessages) return;

        isCheckingForNewMessages = true;

        $.ajax({
            url: `/messenger/customer/${selectedCustomerId}/messages?after=${encodeURIComponent(lastMessageTimestamp)}`,
            method: 'GET',
            success: function(newMessages) {
                if (newMessages.length > 0) {
                    playNewMessageSound();
                    // Reload all messages to show new ones
                    loadMessages();
                }
            },
            error: function(xhr) {
                console.error('Failed to check for new messages');
            },
            complete: function() {
                isCheckingForNewMessages = false;
            }
        });
    }

    function renderMessages(messages) {
        let html = '';
        messages.forEach(function(message) {
            const isFromPage = message.is_echo;
            const messageClass = isFromPage ? 'sent' : 'received';
            const alignClass = isFromPage ? 'text-right' : 'text-left';

            let attachmentsHtml = '';
            if (message.attachments && message.attachments.length > 0) {
                attachmentsHtml = '<div class="attachments mt-2">';
                message.attachments.forEach(function(attachment) {
                    if (attachment && attachment.type === 'image' && attachment.payload && attachment.payload.url) {
                        attachmentsHtml += `
                            <div class="attachment-image mb-2">
                                <img src="${attachment.payload.url}" class="img-fluid rounded" style="max-width: 200px; max-height: 200px;" alt="Attachment">
                            </div>
                        `;
                    } else if (attachment && attachment.payload && attachment.payload.url) {
                        attachmentsHtml += `
                            <div class="attachment-file mb-2">
                                <a href="${attachment.payload.url}" target="_blank" class="btn btn-sm btn-outline-secondary">
                                    <i class="fas fa-download"></i> Download Attachment
                                </a>
                            </div>
                        `;
                    }
                });
                attachmentsHtml += '</div>';
            }

            html += `
                <div class="message ${messageClass} mb-3">
                    <div class="message-bubble p-2 rounded ${isFromPage ? 'bg-primary text-white ml-auto' : 'bg-light'}"
                         style="max-width: 70%; display: inline-block;">
                        ${message.text ? message.text : ''}
                        ${attachmentsHtml}
                        <br>
                        <small class="${isFromPage ? 'text-white-50' : 'text-muted'}">
                            ${new Date(message.sent_at).toLocaleString()}
                        </small>
                    </div>
                </div>
            `;
        });

        $('#messagesContainer').html(html);

        // Scroll to bottom
        $('#chatContainer').scrollTop($('#chatContainer')[0].scrollHeight);
    }

    function loadCustomerAutoReplyStatus() {
        if (!selectedCustomerId) return;

        // Load customer auto reply status
        $.ajax({
            url: `/api/customers/${selectedCustomerId}`,
            method: 'GET',
            success: function(customer) {
                $('#customerAutoReplyToggle').prop('checked', customer.auto_reply_enabled);
            },
            error: function(xhr) {
                console.error('Failed to load customer auto reply status');
            }
        });

        // Load page auto reply status (assuming the customer has messages from a page)
        $.ajax({
            url: `/messenger/customer/${selectedCustomerId}/messages`,
            method: 'GET',
            success: function(messages) {
                if (messages.length > 0) {
                    // Get the page from the first message
                    const pageName = messages[0].page_name;
                    $('#pageAutoReplyBadge').text(`Page: ${pageName}`);
                    // You could add logic here to check page auto reply status if needed
                }
            }
        });
    }

    // Handle customer auto reply toggle
    $('#customerAutoReplyToggle').on('change', function() {
        const enabled = $(this).is(':checked');

        $.ajax({
            url: `/messenger/customer/${selectedCustomerId}/toggle-auto-reply`,
            method: 'POST',
            data: {
                auto_reply_enabled: enabled,
                _token: '{{ csrf_token() }}'
            },
            success: function(response) {
                // Optional: Show success message
            },
            error: function(xhr) {
                // Revert the toggle on error
                $(this).prop('checked', !enabled);
                alert('Failed to update auto reply setting');
            }
        });
    });
});
</script>

<style>
.message.sent {
    text-align: right;
}

.message.received {
    text-align: left;
}

.message-bubble {
    word-wrap: break-word;
}
</style>
@endsection
