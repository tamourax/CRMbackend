<?php

namespace App\Http\Controllers;

use App\Http\Requests\ListCustomersRequest;
use App\Http\Resources\CustomerResource;
use App\Services\CrmQueryService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function __construct(private CrmQueryService $queries) {}

    public function index(ListCustomersRequest $request): AnonymousResourceCollection
    {
        $customers = $this->queries->customers($request->user(), $request->validated())
            ->withCount('deals')
            ->orderBy('company')
            ->orderBy('id')
            ->paginate($request->integer('per_page', 10))
            ->withQueryString();

        return CustomerResource::collection($customers);
    }

    public function show(Request $request, string $id): CustomerResource
    {
        $customer = $this->queries->customers($request->user())
            ->with(['deals' => fn ($query) => $query->orderByDesc('value')])
            ->findOrFail($id);
        Gate::authorize('view', $customer);

        return new CustomerResource($customer);
    }
}
