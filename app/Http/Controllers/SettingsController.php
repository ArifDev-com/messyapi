<?php

namespace App\Http\Controllers;

use App\Models\FacebookPage;
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

        return view('settings.index', compact('pages'));
    }

    public function update(Request $request)
    {
        $request->validate([
            'openai_api_key' => 'nullable|string',
            'whatsapp_api_key' => 'nullable|string',
            'whatsapp_phone_number_id' => 'nullable|string',
        ]);

        $user = Auth::user();
        $user->update([
            'openai_api_key' => $request->openai_api_key,
            'whatsapp_api_key' => $request->whatsapp_api_key,
            'whatsapp_phone_number_id' => $request->whatsapp_phone_number_id,
        ]);

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
