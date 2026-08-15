<?php

namespace App\Http\Middleware;

use App\Enums\Role;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Spatie\Permission\Models\Permission;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),

            'auth' => [
                'user' => $user ? [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'avatar' => $user->avatar,
                    'roles' => $user->getRoleNames(),
                    'permissions' => $this->permissionsFor($user),
                    'is_admin' => $user->isAdmin(),
                ] : null,
            ],

            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],

            'locales' => config('app.supported_locales', ['en', 'km']),
            'locale' => app()->getLocale(),
        ];
    }

    /**
     * Super admin passes every check through Gate::before rather than by
     * holding permission rows, so its stored permission set is empty. Expand
     * it here or the frontend would hide every gated control from the most
     * privileged user.
     */
    private function permissionsFor($user): \Illuminate\Support\Collection
    {
        if ($user->hasRole(Role::SuperAdmin->value)) {
            return Permission::query()->pluck('name');
        }

        return $user->getAllPermissions()->pluck('name');
    }
}
