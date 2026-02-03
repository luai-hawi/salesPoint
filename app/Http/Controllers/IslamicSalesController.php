<?php

namespace App\Http\Controllers;

class IslamicSalesController extends Controller
{
    /**
     * Display the Islamic Sales PWA page
     * This page operates entirely on client-side with local SQLite database
     * No server-side database operations are performed
     */
    public function index()
    {
        return view('islam.index');
    }
}
