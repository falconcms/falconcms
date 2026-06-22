<?php

namespace FalconCms\Core\Http\Controllers\Admin;

use Illuminate\Routing\Controller;
use App\Models\User;
use FalconCms\Core\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_users')) {
            abort(403, 'You do not have permission to manage users.');
        }
        $query = User::with('role');

        if ($request->filled('s')) {
            $query->where(function($q) use ($request) {
                $q->where('name', 'like', '%' . $request->s . '%')
                  ->orWhere('email', 'like', '%' . $request->s . '%')
                  ->orWhere('username', 'like', '%' . $request->s . '%');
            });
        }

        if ($request->filled('role')) {
            $query->whereHas('role', function($q) use ($request) {
                $q->where('slug', $request->role);
            });
        }

        if ($request->status === 'blocked') {
            $query->where(function($q) {
                $q->where('is_blocked', true)
                  ->orWhere(function($sq) {
                      $sq->whereNotNull('blocked_until')
                         ->where('blocked_until', '>', now());
                  });
            });
        }

        $users = $query->latest()->paginate(10)->withQueryString();
        
        $allCount = User::count();
        $roles = Role::all()->map(function($role) {
            $role->count = User::where('role_id', $role->id)->count();
            return $role;
        });

        $blockedCount = User::where('is_blocked', true)
            ->orWhere(function($q) {
                $q->whereNotNull('blocked_until')
                  ->where('blocked_until', '>', now());
            })->count();
        
        $allUsers = User::all(); // For reassignment dropdown
        
        return view('falcon-cms::admin.users.index', compact(
            'users', 'allCount', 'roles', 'blockedCount', 'allUsers'
        ));
    }

    public function create()
    {
        if (!auth()->user()->hasPermission('manage_users')) {
            abort(403);
        }
        $roles = Role::orderBy('name')->get();
        return view('falcon-cms::admin.users.create', compact('roles'));
    }

    public function store(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_users')) {
            abort(403);
        }
        $validated = $request->validate([
            'username' => 'required|string|max:255|unique:users',
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'roles' => 'required|array|min:1',
            'roles.*' => 'exists:roles,id',
        ]);

        $roles = array_values(array_unique(array_map('intval', $request->input('roles', []))));

        // Only a super-admin can assign the Super Admin role.
        if (Role::whereIn('id', $roles)->where('slug', 'super-admin')->exists() && !auth()->user()->hasRole('super-admin')) {
            return redirect()->back()->with('error', 'Only a Super Admin can assign the Super Admin role.')->withInput();
        }

        $user = User::create([
            'username' => $validated['username'],
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'role_id'  => $roles[0] ?? null,
        ]);
        $user->assignRoles($roles);
        falcon_log_activity('created', "Created a new user: {$user->name} ({$user->username})", $user);

        return redirect()->route('admin.users.index')->with('success', 'User created successfully.');
    }

    public function edit(User $user)
    {
        // Without user-management permission a user may only edit their OWN profile.
        if (!auth()->user()->hasPermission('manage_users') && auth()->id() !== $user->id) {
            abort(403);
        }

        // Only super-admin can edit other super-admins
        if ($user->hasRole('super-admin') && !auth()->user()->hasRole('super-admin')) {
            return redirect()->route('admin.users.index')->with('error', 'You do not have permission to edit a Super Admin.');
        }

        $roles = Role::all();
        return view('falcon-cms::admin.users.edit', compact('user', 'roles'));
    }
    public function update(Request $request, User $user)
    {
        if (!auth()->user()->hasPermission('manage_users') && auth()->id() !== $user->id) {
            abort(403);
        }

        $canManageRoles = auth()->user()->isAdmin();

        $rules = [
            'username' => 'required|string|max:255|unique:users,username,' . $user->id,
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users,email,' . $user->id,
            'password' => 'nullable|string|min:8|confirmed',
        ];
        if ($canManageRoles) {
            $rules['roles'] = 'required|array|min:1';
            $rules['roles.*'] = 'exists:roles,id';
        }
        $validated = $request->validate($rules);

        if (!empty($validated['password'])) {
            $validated['password'] = Hash::make($validated['password']);
        } else {
            unset($validated['password']);
        }

        // Protection: Only super-admin can edit other super-admins
        if ($user->hasRole('super-admin') && !auth()->user()->hasRole('super-admin')) {
            return redirect()->route('admin.users.index')->with('error', 'You do not have permission to edit a Super Admin.');
        }

        // Resolve the role set. Only user-managers may change roles; a self-editor
        // (editing their own profile without manage_users) keeps their existing roles,
        // which also prevents self-escalation via the profile page.
        if ($canManageRoles) {
            $roles = array_values(array_unique(array_map('intval', $request->input('roles', []))));
            if (\FalconCms\Core\Models\Role::whereIn('id', $roles)->where('slug', 'super-admin')->exists()
                && !auth()->user()->hasRole('super-admin')) {
                return redirect()->back()->with('error', 'Only a Super Admin can assign the Super Admin role.')->withInput();
            }
        } else {
            $roles = $user->cmsRoleIds();
        }
        if (empty($roles)) $roles = array_values(array_filter([$user->role_id]));

        unset($validated['roles']);
        $user->update($validated);
        $user->assignRoles($roles);
        falcon_log_activity('updated', "Updated user profile: {$user->name}", $user);

        return redirect()->route('admin.users.edit', $user)->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user)
    {
        if (!auth()->user()->hasPermission('manage_users')) {
            abort(403);
        }
        if (auth()->id() === $user->id) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete your own account.');
        }

        if ($user->hasRole('super-admin') && !auth()->user()->hasRole('super-admin')) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot delete a Super Admin account.');
        }

        $deleteOption = $request->input('delete_option', 'delete'); // 'delete' or 'reassign'
        $reassignTo = $request->input('reassign_to');

        DB::beginTransaction();
        try {
            if ($deleteOption === 'reassign' && $reassignTo) {
                // Migrate all posts, pages, and CPTs to the new user
                DB::table('posts')->where('user_id', $user->id)->update(['user_id' => $reassignTo]);
                falcon_log_activity('updated', "Migrated content from deleted user {$user->name} to user ID: {$reassignTo}", $user);
            } else {
                // Delete all content
                DB::table('posts')->where('user_id', $user->id)->delete();
                falcon_log_activity('deleted', "Deleted all content associated with user: {$user->name}", $user);
            }

            $name = $user->name;
            $user->delete();
            DB::commit();

            falcon_log_activity('deleted', "Deleted user: {$name}", $user);
            return redirect()->route('admin.users.index')->with('success', 'User deleted successfully.');
        } catch (\Exception $e) {
            DB::rollBack();
            return redirect()->route('admin.users.index')->with('error', 'An error occurred while deleting the user: ' . $e->getMessage());
        }
    }

    public function toggleBlock(User $user)
    {
        if (!auth()->user()->hasPermission('manage_users')) {
            abort(403);
        }
        if (auth()->id() === $user->id) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot block your own account.');
        }

        if ($user->hasRole('super-admin') && !auth()->user()->hasRole('super-admin')) {
            return redirect()->route('admin.users.index')->with('error', 'You cannot block a Super Admin account.');
        }

        // If user is temporarily blocked, or permanently blocked, we toggle it
        $isCurrentlyBlocked = $user->is_blocked || ($user->blocked_until && $user->blocked_until->isFuture());

        if ($isCurrentlyBlocked) {
            // Unblock
            $user->is_blocked = false;
            $user->login_attempts = 0;
            $user->blocked_until = null;
            $user->last_failed_login_ip = null;
            $user->save();
            falcon_log_activity('updated', "Unblocked user: {$user->name}", $user);
            return redirect()->route('admin.users.index')->with('success', "User has been unblocked successfully.");
        } else {
            // Block
            $user->is_blocked = true;
            $user->save();
            falcon_log_activity('updated', "Blocked user: {$user->name}", $user);
            return redirect()->route('admin.users.index')->with('success', "User has been blocked successfully.");
        }
    }

    public function bulk(Request $request)
    {
        if (!auth()->user()->hasPermission('manage_users')) {
            abort(403);
        }

        $ids = $request->ids;
        $action = $request->action;

        if (empty($ids) || $action === 'Bulk Actions') {
            return redirect()->back()->with('error', 'Please select users and an action.');
        }

        // Prevent self-deletion/blocking
        $ids = array_diff($ids, [auth()->id()]);

        if ($action === 'delete') {
            $users = User::whereIn('id', $ids)->get();
            foreach ($users as $user) {
                $name = $user->name;
                $user->delete();
                falcon_log_activity('deleted', "Deleted user: {$name}", $user);
            }
            return redirect()->back()->with('success', 'Selected users deleted successfully.');
        }

        if ($action === 'block') {
            $users = User::whereIn('id', $ids)->get();
            foreach ($users as $user) {
                $user->update(['is_blocked' => true]);
                falcon_log_activity('updated', "Blocked user: {$user->name}", $user);
            }
            return redirect()->back()->with('success', 'Selected users blocked successfully.');
        }

        if ($action === 'unblock') {
            $users = User::whereIn('id', $ids)->get();
            foreach ($users as $user) {
                $user->update([
                    'is_blocked' => false,
                    'login_attempts' => 0,
                    'blocked_until' => null
                ]);
                falcon_log_activity('updated', "Unblocked user: {$user->name}", $user);
            }
            return redirect()->back()->with('success', 'Selected users unblocked successfully.');
        }

        return redirect()->back();
    }
}
