<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CommunityController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => [], 'message' => 'Not yet implemented']);
    }

    public function store(Request $request): JsonResponse
    {
        return response()->json(['data' => [], 'message' => 'Not yet implemented'], 501);
    }

    public function show(string $id): JsonResponse
    {
        return response()->json(['data' => [], 'message' => 'Not yet implemented'], 501);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        return response()->json(['data' => [], 'message' => 'Not yet implemented'], 501);
    }

    public function destroy(string $id): JsonResponse
    {
        return response()->json(['data' => [], 'message' => 'Not yet implemented'], 501);
    }
}
