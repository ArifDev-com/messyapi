<?php

namespace App\Http\Controllers;

use App\Models\FacebookPage;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function index()
    {
        $facebookAccount = Auth::user()->facebookAccount;

        if (!$facebookAccount) {
            return redirect()->route('dashboard')->with('error', 'Please connect your Facebook account first.');
        }

        $pages = $facebookAccount->pages;

        return view('settings.index', [
            'pages' => $pages, 'settings' => Setting::get(),
        ]);
    }

    public function update(Request $request)
    {
        $request->validate([
            'openai_api_key' => 'nullable|string',
            'whatsapp_api_key' => 'nullable|string',
            'whatsapp_phone_number_id' => 'nullable|string',
            'openai_api_url' => 'nullable|string',
            'openai_api_model' => 'nullable|string',
            'ai_instruction' => 'nullable|string',
        ]);

        if($request->ai_instruction) {
            $k = 'ai_instruction';
            Setting::where('key', $k)->delete();
            Setting::create([
                'key' => $k, 'value' => $request->ai_instruction,
            ]);
            return back()->with('success', 'AI updated successfully.');
        }

        foreach([
            'openai_api_key' => $request->openai_api_key,
            'openai_api_url' => $request->openai_api_url,
            'openai_api_model' => $request->openai_api_model,
            'whatsapp_api_key' => $request->whatsapp_api_key,
            'whatsapp_phone_number_id' => $request->whatsapp_phone_number_id,
        ] as $k => $v) {
            Setting::where('key', $k)->delete();
            Setting::create([
                'key' => $k, 'value' => $v
            ]);
        }

        return back()->with('success', 'Settings updated successfully.');
    }
    public function toggleAutoReply(FacebookPage $page, Request $request)
    {
        if ($page->facebookAccount->user_id !== Auth::id()) {
            abort(403);
        }

        $page->update([
            'auto_reply_enabled' => $request->boolean('auto_reply_enabled')
        ]);

        return response()->json(['success' => true]);
    }
}
