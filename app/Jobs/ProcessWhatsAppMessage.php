<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Kstmostofa\LaravelWhatsApp\Events\Web\MessageReceived;
use Kstmostofa\LaravelWhatsApp\Models\WaMessage;
use Kstmostofa\LaravelWhatsApp\Web\WebClient;
use App\Models\Customer;

class ProcessWhatsAppMessage implements ShouldQueue
{
    use Queueable;

    public $timeout = 120;
    public $tries = 3;
    public $backoff = [5, 30, 60]; // Wait 5s, 30s, 60s between retries


    /**
     * Create a new job instance.
     */
    public function __construct(public MessageReceived $event)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $event = $this->event;
        $payload = $event->payload;
        try {
            $id = $payload['message']['id'];
            $mess = WaMessage::where('wa_message_id', $id)->first();
            if($payload['message']['hasMedia']) {
                $mess->media_path = $this->downloadMedia($mess);
                $mess->save();
            }
            $customer = Customer::getOrCreateCustomerWhatsapp($payload['message']['from']);
            $mess->customer_id = $customer->id;
            $mess->save();

            // send ai reply
//            app(AiS)

        } catch (\Exception $e) {
            Log::error("Failed to process message: " . $e->getMessage());
//            $this->fail($e);
        }
    }

    function downloadMedia(WaMessage $message)
    {
        $messageId = $message->wa_message_id;
        $sidecarUrl = 'http://127.0.0.1:3000';
        $session = 'main';
        $url = "{$sidecarUrl}/sessions/{$session}/messages/{$messageId}/media";

        try {
            $response = Http::timeout(30)->get($url);

            if ($response->successful()) {
                $content = $response->body();
                $contentType = $response->header('Content-Type');
                $headers = $response->headers();

                // Extract filename from Content-Disposition header
                $filename = $message->id . '/' . $this->extractFilenameFromHeaders($headers);

                // If no filename found, generate one from content type
                if (!$filename) {
                    $extension = $message->id . '/' . $this->getExtensionFromMime($headers['Content-Type'][0] ?? 'application/octet-stream');
                    $filename = uniqid() . "." . $extension;
                }

                // Create directory if not exists
                Storage::disk('public')->makeDirectory(dirname($filename));
                // Save file
                Storage::disk('public')->put($filename, $content);

                return $filename;
            }
        } catch (\Exception $e) {
            Log::error("Media download failed: {$e->getMessage()}");
        }

        return null;
    }

    /**
     * Extract filename from Content-Disposition header
     * Handles formats like:
     * - inline; filename="file.pdf"
     * - attachment; filename=file.pdf
     * - inline; filename*=UTF-8''file.pdf
     */
    protected function extractFilenameFromHeaders(array $headers): ?string
    {
        $contentDisposition = $headers['Content-Disposition'][0] ?? null;

        if (!$contentDisposition) {
            return null;
        }

        // Try to match filename="something"
        if (preg_match('/filename="([^"]+)"/', $contentDisposition, $matches)) {
            return $matches[1];
        }

        // Try to match filename=something (without quotes)
        if (preg_match('/filename=([^;]+)/', $contentDisposition, $matches)) {
            return trim($matches[1]);
        }

        // Try to match filename* (RFC 5987 encoding)
        if (preg_match("/filename\*=UTF-8''([^;]+)/", $contentDisposition, $matches)) {
            return urldecode($matches[1]);
        }

        return null;
    }

    protected function getExtensionFromMime(string $mime): string
    {
        $map = [
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'video/mp4' => 'mp4',
            'video/mpeg' => 'mpg',
            'audio/mpeg' => 'mp3',
            'audio/ogg' => 'ogg',
            'application/msword' => 'doc',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx',
            'application/vnd.ms-excel' => 'xls',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'xlsx',
            'text/plain' => 'txt',
        ];

        return $map[$mime] ?? 'bin';
    }
}
