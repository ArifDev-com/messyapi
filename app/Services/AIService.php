<?php
namespace App\Services;

use App\Models\FacebookMessage;
use App\Models\FacebookPage;
use Illuminate\Support\Facades\Cache;
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
            ->timeout(60 * 3);
        }
    }
    function generateMessage(FacebookPage $page, string $senderId, \App\Models\FacebookMessage $message)
    {
        if(!$this->api) return null;
        $requestBody = [
            "model" => $this->model,
            "messages" => [
                [
                    'role' => 'system',
                    'content' => $this->systemPrompt(),
                ],
                ...(FacebookMessage::where('facebook_page_id', $page->id)
                    ->where(fn($q) => $q->where('sender_id', $senderId)->orWhere('recipient_id', $senderId))
                    ->get()
                    ->map(function ($item) {
                        $attachment = "";
                        if($item->attachments && is_array($item->attachments)) {
                            $attachment .= "Attachments:";
                            foreach ($item->attachments as $att) {
                                $attachment .= "\n" . ($att['type'] ?? 'File') . ": ". ($att['payload']['url'] ?? url('/'));
                            }
                        }
                        $mess = ($item->reply_text ?: $item->message_text) . "\n" . $attachment;
                        $mess = trim($mess);
                        if($item->is_echo) {
                            return [
                                'role' => 'system',
                                'content' => $mess,
                            ];
                        }else{
                            return [
                                'role' => 'user',
                                'content' => $mess,
                            ];
                        }
                    })
                    ->toArray())
            ]
        ];
//        Log::info('request body: ', [$requestBody]);
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
            $data = str_replace("\\\"", '"', $data);
            $data = str_replace("```json", '', $data);
            $data = str_replace("```", '', $data);
            $data = preg_replace('/^```json\s*/', '', $data);
            $data = preg_replace('/\s*```$/', '', $data);
            Log::info('debug::', [$data]);
            $data = json_decode($data, true);
            if (is_string($data)) {
                $data = json_decode($data, true);
            }
            if($data) {
                // place the order
                try {
                    Log::info('33', [$data]);
                    $apiResponse = erpClient()->post('/api/order', $data)->json();
                    $response = "Order has been placed, visit our portal to learn more: " . ($apiResponse['link'] ?? '');
                } catch (\Throwable $th) {
                    $response = "Failed to create order: " . $th->getMessage(). ". Please try again.";
                }
            }
        } catch(\Exception $e) {
            Log::error($e);
        }
        return $response;// . "\nby AI";
    }
    function systemPrompt(): string
    {
        $metadata = Cache::remember('option_metadata', 60 * 60 * 12, function() {
            try {
                return erpClient()->get('/api/meta')->json();
            } catch (\Throwable $th) {
                Log::error($th);
            }
            return null;
        });
        Log::info('meta', [$metadata]);
        $into = "You are an AI bot that interacts with our customers indirectly.
we are 'Make my Jersey Bangladesh' company and our address is: House-9, Road-8, Block-E, Section-12, Dhaka 1216, Dhaka.
We have a customer support phone number with whatsapp: 01971564557. If any customer ask for address or contact, please tell them.
Your main task is to answer queries and take jersey orders. orders are complicated, here are the details:

ORDER Requirement:
1. Customer name, valid phone number and address is required.
2. jersey's are always grouped by teams. customer may give team name, otherwise just use sequantial team name.
3. in each team, customer will first have to select a category of a jersey, fabric, neck type, cuff type.
4. in each team, customer can include pants. in that case ask to select type of pant and fabric.
5. in each team, customer can upload multiple logo and font. they can add notes for them too.
6. after giving above required team details, customer now can give details of each jersey of the team.
7. each jersey of a team will have title/name, number, size, sleeve type, have half pant?, note.
8. customer can give an image from his phone or sheet instead of typing each jersey details of a team to save time. he can also do both.
9. after making sure that the customer has given all required data, ask again if the details are correct to place the order.
10. when a order data is ready and customer double checked, you just return the formatted order json object instead of saying anything as text. an algorithm will process that order json data and will respond to the customer with the order link.

About ORDER Data from Ecommerce ERP:
below is the available data of categories, fabrics etc. Make sure keep the exact same title/name of metadata fields in the order json. otherwise Ecommerce ERP will reject order request.
" . json_encode($metadata ?: []) . '

Example Proper FORMAT of ORDER JSON:
{
  "customer": "John doe",
  "phone": "01925339294"
  "note": "",
  "teams": [{
      "title": "Team 1",
      "category": "Sando",
      "category_fabric": "01 INTERLOCK PP",
      "half_sleeve_qty": "1",
      "full_sleeve_qty": "1",
      "pant": "Cargo",
      "pant_fabric": "09 TRICOT FABRIC",
      "pant_quantity": "1",
      "neck": "01 ROUND NECK",
      "cuff": "COLLAR / CUFF",
      "logo_notes": [
        "please keep the logo black and white and crop it to look square"
      ],
      "font_notes": [
        "use this font if you do not find it online."
      ],
      "detail_image": "https://....",
      "details": [
        {
          "title": "John doe",
          "number": "1",
          "size": "2XL",
          "sleeve": "HALF SLEEVE",
          "pant": "Yes",
          "note": "make the shoulder slimmer if possible"
        }
      ],
      "logo_images": [
        "https://...."
      ],
      "font_files": [
        "https://...."
    ]
    }]
}

Policies:
1. Always keep your response short unless customer ask for explanation or details.
2. Always talk in bengali unless customer ask for different language.
3. Always be respective and do not say anything else other than queries or order details.
4. If a customer struggles with order details multiple times, ask them to contact via phone to customer support.
5. once an order is confirmed by any text confirmation (not just "Confirm" text, anything with same meaning), just return the json data of the order in properly formatted structure so that our algorithm can process it and send the order.
6. Do not ask multiple questions at a time.
7. If a customer messages after a order is placed, treat him as new customer to get new order.
        ';

        return $into;
    }
}
