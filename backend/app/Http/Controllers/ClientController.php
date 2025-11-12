<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $clients = Client::query()
            ->when(
                $user->role === 'admin',
                // Admin vê todos os clientes
                function ($query) use ($request) {
                    if ($request->query('owner_id')) {
                        $query->where('owner_id', $request->query('owner_id'));
                    }
                },
                // Owner vê apenas seus clientes, Employee não vê clientes
                function ($query) use ($user) {
                    if ($user->role === 'owner') {
                        $query->where('owner_id', $user->id);
                    } else {
                        // Employee não tem acesso a clientes
                        $query->whereRaw('1 = 0');
                    }
                }
            )
            ->when($request->query('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('cpf', 'like', "%{$search}%");
                });
            })
            ->with('owner:id,name,email')
            ->orderBy('name')
            ->paginate($request->query('per_page', 15));

        return response()->json($clients);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $user = $request->user();

        if ($user->role === 'employee') {
            return response()->json(
                ['message' => 'Funcionários não podem criar clientes.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
            'anamnesis' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'string'], // URL da foto (pode ser base64 ou URL)
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['string', 'max:255'],
        ]);

        $data['owner_id'] = $user->id;

        // Processar foto se for base64
        if (isset($data['photo']) && str_starts_with($data['photo'], 'data:image')) {
            $data['photo'] = $this->savePhoto($data['photo'], $user->id);
        }

        $client = Client::create($data);

        $client->load('owner:id,name,email');

        return response()->json($client, Response::HTTP_CREATED);
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, string $id)
    {
        $user = $request->user();
        $client = Client::with('owner:id,name,email', 'schedulings.service:id,name')
            ->findOrFail($id);

        // Verificar permissão
        if ($user->role !== 'admin' && $client->owner_id !== $user->id) {
            return response()->json(
                ['message' => 'Não autorizado.'],
                Response::HTTP_FORBIDDEN
            );
        }

        return response()->json($client);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $user = $request->user();
        $client = Client::findOrFail($id);

        // Verificar permissão
        if ($user->role === 'employee') {
            return response()->json(
                ['message' => 'Funcionários não podem editar clientes.'],
                Response::HTTP_FORBIDDEN
            );
        }

        if ($user->role !== 'admin' && $client->owner_id !== $user->id) {
            return response()->json(
                ['message' => 'Não autorizado.'],
                Response::HTTP_FORBIDDEN
            );
        }

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'email' => ['nullable', 'email', 'max:255'],
            'cpf' => ['nullable', 'string', 'max:14'],
            'birth_date' => ['nullable', 'date'],
            'address' => ['nullable', 'string'],
            'anamnesis' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'photo' => ['nullable', 'string'],
            'allergies' => ['nullable', 'array'],
            'allergies.*' => ['string', 'max:255'],
        ]);

        // Processar foto se for base64
        if (isset($data['photo']) && str_starts_with($data['photo'], 'data:image')) {
            // Deletar foto antiga se existir
            if ($client->photo && Storage::disk('public')->exists($client->photo)) {
                Storage::disk('public')->delete($client->photo);
            }
            $data['photo'] = $this->savePhoto($data['photo'], $client->owner_id);
        }

        $client->update($data);
        $client->load('owner:id,name,email');

        return response()->json($client);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, string $id)
    {
        $user = $request->user();
        $client = Client::findOrFail($id);

        // Verificar permissão
        if ($user->role === 'employee') {
            return response()->json(
                ['message' => 'Funcionários não podem excluir clientes.'],
                Response::HTTP_FORBIDDEN
            );
        }

        if ($user->role !== 'admin' && $client->owner_id !== $user->id) {
            return response()->json(
                ['message' => 'Não autorizado.'],
                Response::HTTP_FORBIDDEN
            );
        }

        // Deletar foto se existir
        if ($client->photo && Storage::disk('public')->exists($client->photo)) {
            Storage::disk('public')->delete($client->photo);
        }

        $client->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Salva foto em base64 para o storage
     */
    private function savePhoto(string $base64, int $ownerId): string
    {
        // Extrair dados da imagem
        if (preg_match('/^data:image\/(\w+);base64,/', $base64, $matches)) {
            $extension = $matches[1];
            $imageData = base64_decode(substr($base64, strpos($base64, ',') + 1));

            // Gerar nome único
            $filename = 'clients/' . $ownerId . '/' . uniqid() . '.' . $extension;

            // Salvar no storage
            Storage::disk('public')->put($filename, $imageData);

            return $filename;
        }

        // Se não for base64, retornar como está (pode ser URL)
        return $base64;
    }
}
