<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ConfigController extends Controller
{
    public function firebaseConfig(): JsonResponse
    {
        return response()->json(config('services.firebase.client'));
    }
}