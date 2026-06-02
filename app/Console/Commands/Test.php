<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use App\Models\Setting;

class Test extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wow';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Execute the console command.
     */
    public function handle()
    {
//        $key = Setting::where('key', 'openai_api_key')->first()?->value;
//        $url = Setting::where('key', 'openai_api_url')->first()?->value;
//        $model = Setting::where('key', 'openai_api_model')->first()?->value;
//        $api = Http::withHeaders([
//            "Authorization" => "Bearer " . $key,
//            'HTTP-Referer' => url('/'),
//            'X-OpenRouter-Title' => config('app.name'),
//        ])->timeout(120)
//        ->post($url, json_decode('{"model":"google/gemini-3-flash-preview","messages":[{"role":"user","content":"ok"},{"role":"user","content":"f"},{"role":"user","content":"Ok"},{"role":"user","content":"test"},{"role":"user","content":"any order"},{"role":"user","content":"ok"},{"role":"user","content":"hello"},{"role":"user","content":"test 2"},{"role":"user","content":"test 4"},{"role":"user","content":"test 3"},{"role":"user","content":"test 3"},{"role":"user","content":"test 5"},{"role":"user","content":"test 3"},{"role":"user","content":"test 6"},{"role":"user","content":"test 3"},{"role":"user","content":"test 3"}]}', true));
//        $body = $api->json();

//        $metadata = Cache::remember('option_metadata1', 60 * 60 * 1, function() {
//            return erpClient()->get('/api/meta')->json();
//        });
//        dump($metadata);
    }
}
