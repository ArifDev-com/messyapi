

# Install the sidecar (downloads Chromium ~600MB)
php artisan whatsapp:sidecar:install

# Start the sidecar service
php artisan whatsapp:sidecar:start

# Listen for incoming messages (run under Supervisor in production)
php artisan whatsapp:web:listen main &
php artisan app:whatsapp-listen &

Then visit /whatsapp/sessions

```php  
use Kstmostofa\LaravelWhatsApp\Facades\WhatsApp;

// Send from your personal number
WhatsApp::web('main')->messages()->sendText('966512345678@c.us', 'Hello from my personal number!');

// Listen for incoming messages
Event::listen(\Kstmostofa\LaravelWhatsApp\Events\Web\MessageReceived::class, function ($event) {
$from = $event->from();   // Format: "966512345678@c.us"
$body = $event->body();

    // Your AI logic here
    $reply = aiRespond($body);
    WhatsApp::web('main')->messages()->sendText($from, $reply);
});
```
