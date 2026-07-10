<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Api\V1\BaseController;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class UserController extends BaseController
{
    /**
     * Tüm kullanıcılar listesi (SuperAdmin için)
     */
    public function index(Request $request): JsonResponse
    {
        $query = User::with(['company:id,name', 'roles'])
            ->where('type', '!=', 'super_admin');

        // Firma filtresi
        if ($request->has('company_id')) {
            $query->where('company_id', $request->company_id);
        }

        // Arama
        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%");
            });
        }

        // Durum filtresi
        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        // Tip filtresi
        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        // Sıralama
        $sortBy = $request->get('sort_by', 'created_at');
        $sortDir = $request->get('sort_dir', 'desc');
        $query->orderBy($sortBy, $sortDir);

        $users = $query->paginate($request->get('per_page', 15));

        return $this->paginated($users, 'Kullanıcılar listelendi');
    }

    /**
     * Kullanıcı detay
     */
    public function show(User $user): JsonResponse
    {
        $user->load(['company', 'roles', 'createdBy:id,name']);

        return $this->success($user);
    }
}

