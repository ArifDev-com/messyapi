@extends('layouts.app')

@section('content')
<div class="container">
    <div class="row">
        <div class="col-12">
            <h1 class="mb-4">{{ __('Dashboard') }}</h1>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">{{ $totalMessages }}</h5>
                            <p class="card-text">Total Messages</p>
                        </div>
                        <i class="fas fa-comments fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">{{ $totalCustomers }}</h5>
                            <p class="card-text">Total Customers</p>
                        </div>
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">{{ $totalPages }}</h5>
                            <p class="card-text">Facebook Pages</p>
                        </div>
                        <i class="fab fa-facebook fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h5 class="card-title">{{ $messagesByType['customer_messages'] ?? 0 }}</h5>
                            <p class="card-text">Customer Messages</p>
                        </div>
                        <i class="fas fa-inbox fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Messages by Type</h5>
                </div>
                <div class="card-body">
                    <canvas id="messagesByTypeChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Messages Over Time (Last 30 Days)</h5>
                </div>
                <div class="card-body">
                    <canvas id="messagesOverTimeChart" width="400" height="200"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Top Pages and Recent Messages -->
    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Top Pages by Message Count</h5>
                </div>
                <div class="card-body">
                    @if($topPages->count() > 0)
                        @foreach($topPages as $page)
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <span>{{ $page->name }}</span>
                                <span class="badge badge-primary">{{ $page->messages_count }}</span>
                            </div>
                        @endforeach
                    @else
                        <p class="text-muted">No data available</p>
                    @endif
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5>Recent Messages</h5>
                </div>
                <div class="card-body">
                    @if($recentMessages->count() > 0)
                        @foreach($recentMessages as $message)
                            <div class="border-bottom pb-2 mb-2">
                                <small class="text-muted">
                                    {{ $message->customer ? $message->customer->name : 'Unknown' }}
                                    @ {{ $message->facebookPage->name }}
                                    ({{ $message->sent_at->diffForHumans() }})
                                </small>
                                <p class="mb-0">{{ Str::limit($message->message_text, 50) }}</p>
                            </div>
                        @endforeach
                    @else
                        <p class="text-muted">No recent messages</p>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <!-- Actions -->
    <div class="row mt-4">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <h5>Quick Actions</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        @if(!Auth::user()->facebookAccount)
                        <div class="col-md-4">
                            <a href="{{ route('facebook.connect') }}" class="btn btn-primary btn-block">
                                <i class="fab fa-facebook-messenger"></i> Connect Facebook
                            </a>
                        </div>
                        @else
                        <div class="col-md-3">
                            <a href="{{ route('settings.index') }}" class="btn btn-secondary btn-block">
                                <i class="fas fa-cog"></i> Settings
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('messenger.index') }}" class="btn btn-info btn-block">
                                <i class="fas fa-comments"></i> Messenger
                            </a>
                        </div>
                        <div class="col-md-3">
                            <a href="{{ route('facebook.pages') }}" class="btn btn-primary btn-block">
                                <i class="fab fa-facebook-messenger"></i> Manage Pages
                            </a>
                        </div>
                        @endif
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Messages by Type Chart
    const messagesByTypeCtx = document.getElementById('messagesByTypeChart').getContext('2d');
    const messagesByTypeData = @json($messagesByType);

    new Chart(messagesByTypeCtx, {
        type: 'pie',
        data: {
            labels: ['Customer Messages', 'Replies'],
            datasets: [{
                data: [
                    messagesByTypeData.customer_messages || 0,
                    messagesByTypeData.replies || 0
                ],
                backgroundColor: [
                    '#007bff',
                    '#28a745'
                ]
            }]
        },
        options: {
            responsive: true,
            plugins: {
                legend: {
                    position: 'bottom',
                }
            }
        }
    });

    // Messages Over Time Chart
    const messagesOverTimeCtx = document.getElementById('messagesOverTimeChart').getContext('2d');
    const messagesOverTimeData = @json($messagesOverTime);

    new Chart(messagesOverTimeCtx, {
        type: 'line',
        data: {
            labels: messagesOverTimeData.map(item => item.date),
            datasets: [{
                label: 'Messages',
                data: messagesOverTimeData.map(item => item.count),
                borderColor: '#007bff',
                backgroundColor: 'rgba(0, 123, 255, 0.1)',
                tension: 0.1
            }]
        },
        options: {
            responsive: true,
            scales: {
                y: {
                    beginAtZero: true
                }
            }
        }
    });
});
</script>
@endsection
