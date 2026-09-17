<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;

class ServiceController extends Controller
{
    /** GET /services */
    public function index()
    {
        return response()->json([
            'services' => Service::all(['id', 'nom', 'description']),
        ]);
    }
}
