<?php

namespace App\Http\Controllers;

use App\Services\SalesReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SalesReportController extends Controller
{
    public function __invoke(Request $request, SalesReportService $reports): JsonResponse
    {
        return response()->json(['data' => $reports->generate($request->user())]);
    }
}
