<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OfflineController extends Controller
{
    public function csrf(Request $request): JsonResponse
    {
        $request->session()->regenerateToken();

        return response()->json([
            'token' => csrf_token(),
        ])->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0');
    }

    public function index(): Response
    {
        return response()
            ->view('offline.index')
            ->header('Cache-Control', 'private, max-age=300');
    }

    public function queue(): Response
    {
        return response()
            ->view('offline.queue')
            ->header('Cache-Control', 'private, max-age=300');
    }
}
