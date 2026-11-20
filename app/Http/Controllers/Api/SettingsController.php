<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    /** Adresse API définie sur le site web, réservée au super administrateur. */
    public function apiUrl(Request $request): JsonResponse
    {
        if (! $request->user()->isSuperAdmin()) {
            abort(403, 'Seul le super administrateur peut consulter cette configuration.');
        }

        return response()->json([
            'api_url' => Setting::get('api_url', 'http://172.16.206.66:8000/api'),
        ]);
    }
}
