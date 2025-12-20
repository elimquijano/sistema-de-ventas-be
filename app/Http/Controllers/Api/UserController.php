<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        $query = User::with('roles');
        $user = auth()->user();

        // A non-super admin can only see their own business
        if ($user->business_id) {
            $query->where('business_id', $user->business_id);
        }

        if ($request->has('search')) {
            $query->search($request->search);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('role')) {
            $query->role($request->role);
        }

        $perPage = $this->getPaginationSize($request, $query);
        $users = $query->paginate($perPage);

        return response()->json($users);
    }

    public function store(StoreUserRequest $request)
    {
        $creator = auth()->user();
        $data = $request->validated();

        $data['password'] = Hash::make($data['password']);

        if ($creator->business_id) {
            $data['business_id'] = $creator->business_id;
        }

        $user = User::create($data);

        if ($request->has('role_ids')) {
            $user->syncRoles($request->role_ids);
        }

        return response()->json($user->load('roles'), 201);
    }

    public function show(User $user)
    {
        return response()->json($user->load('roles.permissions'));
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        $data = $request->validated();
        $updater = auth()->user();

        // Prevent non-super-admins from changing the business_id
        if ($updater->business_id) {
            unset($data['business_id']);
        }

        if ($request->has('password') && !empty($request->password)) {
            $data['password'] = Hash::make($request->password);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        if ($request->has('role_ids')) {
            $user->syncRoles($request->role_ids);
        }

        return response()->json($user->load('roles'));
    }

    public function destroy(User $user)
    {
        if ($user->id === auth()->id()) {
            return response()->json(['message' => 'Cannot delete your own account'], 422);
        }

        $user->delete();

        return response()->json(['message' => 'User deleted successfully']);
    }

    public function updateStatus(Request $request, User $user)
    {
        $request->validate([
            'status' => 'required|in:active,inactive,pending',
        ]);

        $user->update(['status' => $request->status]);

        return response()->json($user);
    }

    public function assignRoles(Request $request, User $user)
    {
        $request->validate([
            'role_ids' => 'required|array',
            'role_ids.*' => 'exists:roles,id',
        ]);

        $user->syncRoles($request->role_ids);

        return response()->json($user->load('roles'));
    }
}
