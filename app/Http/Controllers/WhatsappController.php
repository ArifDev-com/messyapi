<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class WhatsappController extends Controller
{
    function index()
    {
        return view('whatsapp.index');
    }
}
