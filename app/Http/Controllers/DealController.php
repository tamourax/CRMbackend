<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListDealsRequest;
use App\Http\Resources\DealResource;
use App\Services\CrmQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class DealController extends Controller
{
    public function __construct(private CrmQueryService $queries) {}

    public function index(ListDealsRequest $request): AnonymousResourceCollection
    {
        $query = $this->queries->deals($request->user(), $request->validated())
            ->with('customer:id,name,company,industry,city,translations');
        $deals = $this->queries->sortDeals($query, $request->input('sort'))
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return DealResource::collection($deals);
    }

    public function show(Request $request, string $id): DealResource
    {
        $deal = $this->queries->findDeal($request->user(), $id, ['customer']);

        return new DealResource($deal);
    }
}
