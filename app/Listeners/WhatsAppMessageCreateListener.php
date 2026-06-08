<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Kstmostofa\LaravelWhatsApp\Events\Web\MessageCreated;
use Kstmostofa\LaravelWhatsApp\Models\WaMessage;
use App\Models\Customer;

class WhatsAppMessageCreateListener
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
    public function handle(MessageCreated $event): void
    {
        $waMessageId = $event->payload['message']['id'] ?? null;

        if (!$waMessageId) {
            dump("No message ID found");
            return;
        }

        $existing = WaMessage::where('wa_message_id', $waMessageId)->exists();

        if(!$existing) {

            try {
                $message = WaMessage::create([
                    'wa_message_id' => $waMessageId,
                    'backend' => 'web',
                    'session_id' => $event->payload['sessionId'] ?? 'main',
                    'direction' => 'outbound',
                    'chat_id' => $event->payload['message']['to'] ?? null,
                    'from_id' => $event->payload['message']['from'] ?? null,
                    'to_id' => $event->payload['message']['to'] ?? null,
                    'type' => $event->payload['message']['type'] ?? null,
                    'body' => $event->payload['message']['body'] ?? null,
                    'payload' => $event->payload['message'],
                    'status' => $event->payload['message']['status'] ?? 'sent',
                    'wa_timestamp' => now(),
                    'customer_id' => Customer::getOrCreateCustomerWhatsapp($event->payload['message']['from'])->id,
                ]);

            } catch (\Exception $e) {
                dump("Error: " . $e->getMessage());
            }
        }
    }
}
