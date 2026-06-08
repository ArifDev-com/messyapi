<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Jobs\ProcessWhatsAppMessage;
use Illuminate\Support\Facades\Log;
use Kstmostofa\LaravelWhatsApp\Events\Web\MessageReceived;
use Kstmostofa\LaravelWhatsApp\Events\Web\MessageC;

class WhatsAppMessageListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(MessageReceived $event)  // ← Type-hint triggers auto-discovery
    {
        ProcessWhatsAppMessage::dispatch(
            $event
        );
    }
}
