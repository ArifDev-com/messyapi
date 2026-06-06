<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Contracts\Events\Dispatcher;
use Kstmostofa\LaravelWhatsApp\Web\WebClient;

class WhatsappListen extends Command
{
    protected $signature = 'app:whatsapp-listen {session? : Session ID to subscribe to}
        {--reconnect-delay=2 : Seconds to wait before reconnecting on dropped stream}
        {--debug : Enable debug logging}';

    protected $description = 'Listen to WhatsApp events with reliable SSE connection';

    protected string $buffer = '';
    protected int $lastActivity = 0;
    protected int $heartbeatTimeout = 30; // Seconds without data triggers reconnect

    public function handle(WebClient $client, Dispatcher $events): int
    {
        $sessionId = $this->argument('session')
            ?: (string) config('laravel-whatsapp.ui.default_session', 'main');

        if ($sessionId === '') {
            $this->error('No session specified and no default_session configured.');
            return self::FAILURE;
        }

        $eventMap = config('laravel-whatsapp.web.events', []);
        $delay = max(1, (int) $this->option('reconnect-delay'));

        $url = sprintf('http://%s:%d/sessions/%s/events',
            $client->host(),
            $client->port(),
            rawurlencode($sessionId),
        );

        $this->info("Subscribing to {$url}");

        if ($this->option('debug')) {
            $this->info("Debug mode enabled - showing raw event data");
        }

        while (! $this->shouldQuit()) {
            $this->buffer = '';
            $this->lastActivity = time();

            $error = $this->consume($url, $client->token(), $sessionId, $eventMap, $events);

            if ($this->shouldQuit()) {
                break;
            }

            if ($error) {
                $this->warn("Stream dropped: {$error}. Reconnecting in {$delay}s…");
            } else {
                $this->warn("Stream closed. Reconnecting in {$delay}s…");
            }

            sleep($delay);
        }

        return self::SUCCESS;
    }

    protected function consume(string $url, ?string $token, string $sessionId, array $eventMap, Dispatcher $events): ?string
    {
        $ch = curl_init($url);

        if (!$ch) {
            return 'curl_init failed';
        }

        $headers = ['Accept: text/event-stream', 'Cache-Control: no-cache'];
        if ($token) {
            $headers[] = 'Authorization: Bearer '.$token;
        }

        // CRITICAL FIX: Add these options to keep connection alive
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 0,  // No timeout
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_HEADERFUNCTION => [$this, 'handleHeader'], // Add header callback
            CURLOPT_WRITEFUNCTION => function ($ch, $data) use ($sessionId, $eventMap, $events) {
                // Update activity timestamp
                $this->lastActivity = time();

                // Debug raw data
                if ($this->option('debug')) {
                    $this->line("[RAW] " . bin2hex(substr($data, 0, 100)) . "...");
                }

                // Append to buffer
                $this->buffer .= $data;

                // Parse all complete events in buffer
                $this->parseBuffer($sessionId, $eventMap, $events);

                return strlen($data);
            },
            CURLOPT_LOW_SPEED_LIMIT => 1,  // Trigger timeout if very slow
            CURLOPT_LOW_SPEED_TIME => $this->heartbeatTimeout,
            CURLOPT_TCP_KEEPALIVE => 1,  // Enable TCP keepalive
            CURLOPT_TCP_KEEPIDLE => 5,   // Start keepalive after 5 seconds
            CURLOPT_TCP_KEEPINTVL => 5,  // Keepalive interval
        ]);

        // Set up signal handling for clean shutdown
        if (function_exists('pcntl_signal')) {
            pcntl_signal(SIGINT, function() use ($ch) {
                $this->info("\nReceived SIGINT, shutting down...");
                curl_close($ch);
                exit(0);
            });
        }

        $this->info("Connected to SSE stream, waiting for events...");

        // Execute the request - this will block until connection drops
        $ok = curl_exec($ch);
        $err = curl_errno($ch) ? curl_error($ch) : null;
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);

        if ($ok === false && $err) {
            return $err;
        }

        if ($httpCode >= 400) {
            return "HTTP {$httpCode}";
        }

        return null;
    }

    protected function handleHeader($ch, string $header): int
    {
        if ($this->option('debug')) {
            $this->line("[HEADER] " . trim($header));
        }
        return strlen($header);
    }

    protected function parseBuffer(string $sessionId, array $eventMap, Dispatcher $events): void
    {
        // Parse all complete SSE events in the buffer
        while (($eot = strpos($this->buffer, "\n\n")) !== false) {
            $rawEvent = substr($this->buffer, 0, $eot);
            $this->buffer = substr($this->buffer, $eot + 2);

            if ($this->option('debug')) {
                $this->line("[EVENT] " . substr($rawEvent, 0, 200));
            }

            [$eventName, $data] = $this->parseSseEvent($rawEvent);

            if ($eventName !== null) {
                $this->info("Received event: {$eventName}");
                $this->dispatchEvent($eventName, $data, $sessionId, $eventMap, $events);
            } elseif ($data !== '') {
                // No event name, but has data - treat as message
                if ($this->option('debug')) {
                    $this->warn("Event without name, data: " . substr($data, 0, 100));
                }
                $this->dispatchEvent('message', $data, $sessionId, $eventMap, $events);
            }
        }
    }

    protected function parseSseEvent(string $raw): array
    {
        $eventName = null;
        $data = '';

        $lines = explode("\n", $raw);
        foreach ($lines as $line) {
            $line = rtrim($line, "\r");

            if ($line === '' || str_starts_with($line, ':')) {
                continue;
            }

            if (str_starts_with($line, 'event:')) {
                $eventName = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $dataPart = ltrim(substr($line, 5));
                $data .= ($data === '' ? '' : "\n") . $dataPart;
            }
        }

        return [$eventName, $data];
    }

    protected function dispatchEvent(string $event, string $json, string $sessionId, array $eventMap, Dispatcher $events): void
    {
        if (empty($json)) {
            return;
        }

        $eventClass = $eventMap[$event] ?? null;

        // Fallback: try to find event class by convention
        if (!$eventClass) {
            $possibleClass = "Kstmostofa\\LaravelWhatsApp\\Events\\Web\\" . ucfirst($event);
            if (class_exists($possibleClass)) {
                $eventClass = $possibleClass;
            }
        }

        if (!$eventClass || !class_exists($eventClass)) {
            if ($this->option('debug')) {
                $this->warn("No event class for: {$event}");
            }
            return;
        }

        $payload = json_decode($json, true);
        if (!is_array($payload)) {
            $payload = ['raw' => $json];
        }

        try {
            $eventObject = new $eventClass($sessionId, $payload);
            $events->dispatch($eventObject);

            if ($this->option('debug')) {
                $this->line("Dispatched {$eventClass}");
            }
        } catch (\Exception $e) {
            $this->error("Failed to dispatch event: " . $e->getMessage());
        }
    }

    protected function shouldQuit(): bool
    {
        // Check for heartbeats (if no data for X seconds, reconnect)
        if (time() - $this->lastActivity > $this->heartbeatTimeout + 10) {
            $this->warn("No data received for " . (time() - $this->lastActivity) . " seconds, reconnecting...");
            return false; // Let the loop handle reconnection
        }

        return false;
    }
}
