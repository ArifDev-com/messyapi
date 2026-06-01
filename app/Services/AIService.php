<?php
namespace App\Services;

use App\Models\FacebookMessage;
use App\Models\FacebookPage;
use Illuminate\Support\Facades\Http;
use App\Models\Setting;
use Log;

class AIService {
    protected $api = null;
    protected $url = null;
    protected $model = null;
    protected $orderJsonDivider = '### Order Json Divider:';
    protected $messageDivider = '### Message Divider:';
    function __construct()
    {
        $key = Setting::where('key', 'openai_api_key')->first()?->value;
        $this->url = Setting::where('key', 'openai_api_url')->first()?->value;
        $this->model = Setting::where('key', 'openai_api_model')->first()?->value;
        if($key) {
            $this->api = Http::withHeaders([
                "Authorization" => "Bearer " . $key,
                'HTTP-Referer' => url('/'),
                'X-OpenRouter-Title' => config('app.name'),
            ])
            ->timeout(120);
        }
    }
    function generateMessage(FacebookPage $page, string $senderId, \App\Models\FacebookMessage $message)
    {
        if(!$this->api) return null;
        $requestBody = [
            "model" => $this->model,
            "messages" => FacebookMessage::where('facebook_page_id', $page->id)
                ->where('sender_id', $senderId)
                ->get()
                ->map(function ($item) {
                    if($item->is_echo) {
                        return [
                            'role' => 'system',
                            'content' => $item->reply_text ?: $item->message_text,
                        ];
                    }else{
                        return [
                            'role' => 'user',
                            'content' => $item->reply_text ?: $item->message_text,
                        ];
                    }
                })
                ->toArray()
        ];
        $requestBody['messages'] = array_merge([[
            'role' => 'system',
            'content' => $this->systemPrompt(),
        ]], $requestBody['messages']);
        $body = $this->api->post($this->url, $requestBody)->json();
        $response = $body['choices'][0]['message']['content'] ?? null;
        $response = $this->checkAndFilterMessage($response);
        return $response;
    }
    function checkAndFilterMessage(string $response): string
    {
        $data = trim($response ?? '');
        try {
            $data = str_replace('\\"', '"', $data);
            $data = json_decode($data, true);
            if($data) {
                // place the order

                Log::info('33', [$data]);
                $response = "Order has been placed, visit our portal to learn more: https://makemyjersey.com/orders/1";
            }
        } catch(\Exception $e) {
            Log::error($e);
        }
        return $response . "\nby AI";
    }
    function systemPrompt(): string
    {
        $into = "You are a AI Assistant that helps taking online orders for our Jersey production house. You also helps by answer quearies. \nNothing else. if a user ask anything inappropriate or irrelevent, just ask to stay on topic or directly call our support hotline. Keep your response short. Do not explain unless asked. always try to use bengali unless asked to change.";
        $into .= "\nOur Company name is 'Make my Jersey Bangladesh', Address: House-9, Road-8, Block-E, Section-12, Dhaka 1216, Dhaka.";
        $into .= "\nContact Phone with Whatsapp: 01971564557. Do not send office details or phone unless customer asks.";

        $into .= "\nWhen a customer give all the required information for an order and ask to place the order with a confirmation, then ";
        $into .= "\nprepare the order json and send it as reply. the reply will go through a function to detect order json. if detected then it will send the data to our";
        $into .= "Erp system to save the order and return an order url. otherwise the message will be relayed to the customer.";

        $into .= "\nDo not return the order json anymore once the order is placed, we do not allow order update.";
        return $into;
    }
}
