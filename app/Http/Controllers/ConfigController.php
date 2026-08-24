<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

/**
 * ConfigController
 *
 * Handles configuration endpoints for frontend clients.
 * Returns only public configuration values.
 *
 * @package App\Http\Controllers
 */
class ConfigController extends Controller
{
    /**
     * Return public Firebase configuration.
     *
     * @return JsonResponse
     */
    public function firebaseConfig(): JsonResponse
    {
        return response()->json([
            'apiKey' => config('services.firebase.client.apiKey'),
            'authDomain' => config('services.firebase.client.authDomain'),
            'databaseURL' => config('services.firebase.client.databaseURL'),
            'projectId' => config('services.firebase.client.projectId'),
            'storageBucket' => config('services.firebase.client.storageBucket'),
            'messagingSenderId' => config('services.firebase.client.messagingSenderId'),
            'appId' => config('services.firebase.client.appId'),
            'measurementId' => config('services.firebase.client.measurementId'),
        ]);
    }
}
