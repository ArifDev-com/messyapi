<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\Customer;
use App\Models\Setting;

class WhatsappController extends Controller
{
    function index()
    {
        return view('whatsapp.index');
    }

    public function toggleAutoReply($user_id, Request $request)
    {
        if($user_id == '0') {
            if($request->boolean('auto_reply_enabled')) {
                Setting::where('key', 'disable_whatsapp')->delete();
            }else {
                Setting::create(['key' => 'disable_whatsapp', 'value' => '1']);
            }
        } else {
            $customer = Customer::getOrCreateCustomerWhatsapp($user_id);
            $customer->update([
                'auto_reply_enabled' => $request->boolean('auto_reply_enabled')
            ]);
        }

        return response()->json(['success' => true]);
    }
}
