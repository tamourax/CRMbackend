<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListCustomersRequest;
use App\Http\Requests\ListDealsRequest;
use App\Http\Resources\CustomerResource;
use App\Http\Resources\DealResource;
use App\Services\CrmQueries;
use App\Services\SalesReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CrmController extends Controller
{
    public function __construct(private CrmQueries $queries) {}

    public function customers(ListCustomersRequest $request): AnonymousResourceCollection
    {
        return CustomerResource::collection($this->queries->customers($request->user(), $request->validated())->withCount('deals')->orderBy('company')->orderBy('id')->paginate($request->integer('per_page', 10))->withQueryString());
    }

    public function customer(Request $request, string $id): CustomerResource
    {
        $customer = $this->queries->customers($request->user())->with(['deals' => fn ($query) => $query->orderByDesc('value')])->findOrFail($id);
        Gate::authorize('view', $customer);

        return new CustomerResource($customer);
    }

    public function deals(ListDealsRequest $request): AnonymousResourceCollection
    {
        $query = $this->queries->deals($request->user(), $request->validated())->with('customer:id,name,company,industry,city,translations');

        return DealResource::collection($this->queries->sortDeals($query, $request->input('sort'))->paginate($request->integer('per_page', 10))->withQueryString());
    }

    public function deal(Request $request, string $id): DealResource
    {
        $deal = $this->queries->deals($request->user())->with('customer')->findOrFail($id);
        Gate::authorize('view', $deal);

        return new DealResource($deal);
    }

    public function report(Request $request, SalesReportService $reports): JsonResponse
    {
        return response()->json(['data' => $reports->generate($request->user())]);
    }
}
