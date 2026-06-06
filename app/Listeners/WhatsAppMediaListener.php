<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Kstmostofa\LaravelWhatsApp\Events\Web\MediaMessageReceived;
use App\Jobs\ProcessWhatsAppMessage;
class WhatsAppMediaListener
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
    public function handle(MediaMessageReceived $event)
    {
        // The event contains file info like URL, mimetype, filename
        $mediaUrl = $event->fileUrl();
        $caption = $event->caption(); // The text user wrote with the image
        $from = $event->from();

        // Dispatch to queue - pass the media URL so AI can "see" it
        ProcessWhatsAppMessage::dispatch($from, $caption, $event->message()->id(), $mediaUrl);
    }
}
