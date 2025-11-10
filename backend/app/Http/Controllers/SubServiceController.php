<?php

namespace App\Http\Controllers;

use App\Models\SubService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SubServiceController extends Controller
{
    public function index(Request $request)
    {
        $subServices = SubService::query()
            ->with('service:id,name,establishment_id')
            ->when(
                $request->query('service_id'),
                fn ($query, $serviceId) => $query->where('service_id', $serviceId)
            )
            ->orderByDesc('created_at')
            ->paginate($request->query('per_page', 15));

        return response()->json($subServices);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'price' => ['required', 'numeric', 'between:0,999999.99'],
            'service_id' => ['required', 'integer', 'exists:services,id'],
        ]);

        $subService = SubService::create($data)->load('service:id,name,establishment_id');

        return response()->json($subService, Response::HTTP_CREATED);
    }

    public function show(SubService $subService)
    {
        $subService->load('service:id,name,establishment_id');

        return response()->json($subService);
    }

    public function update(Request $request, SubService $subService)
    {
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['sometimes', 'nullable', 'string'],
            'price' => ['sometimes', 'numeric', 'between:0,999999.99'],
            'service_id' => ['sometimes', 'integer', 'exists:services,id'],
        ]);

        $subService->update($data);

        return response()->json($subService->refresh()->load('service:id,name,establishment_id'));
    }

    public function destroy(SubService $subService)
    {
        $subService->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }
}

