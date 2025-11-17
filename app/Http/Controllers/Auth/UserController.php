<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UserStoreRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Models\Entity;
use App\Services\UserService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\Rules;

class UserController extends Controller
{
    public function __construct(
        private UserService $userService,
    ) {}

    public function index(Request $request)
    {
        $filters = $request->only([
            'search',
            'role',
            'is_active',
            'warehouse_id',
            'sort_by',
            'sort_order',
        ]);

        $perPage = $request->input('per_page', 15);

        // Pasar filtros al servicio
        $users = $this->userService->getFiltered($filters, $perPage);

        return UserResource::collection($users);
    }
    public function store(UserStoreRequest $request)
    {
        $validated = $request->validated();

        $user = $this->userService->createUser($validated);

        return response()->json([
            'message' => 'Usuario creado correctamente.',
            'user' => $user,
        ], 201);
    }
}
