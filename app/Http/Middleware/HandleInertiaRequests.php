<?php

namespace App\Http\Middleware;

use App\Services\AcademicTermService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        // Fetch the current term once per request
        $currentTerm = (new AcademicTermService())->getCurrent();

        return array_merge(parent::share($request), [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => $request->user(),
            ],
            'flash' => [
                'success' => $request->session()->get('success'),
                'error'   => $request->session()->get('error'),
            ],

            // Share globally as 'currentTerm' prop
            'currentTerm' => $currentTerm ? [
                'id'             => $currentTerm->id,
                'school_year'    => $currentTerm->school_year_label, 
                'semester_label' => $currentTerm->label,             
                'year_start'     => $currentTerm->year_start,
                'semester'       => $currentTerm->semester->value,
            ] : null,
        ]);
    }
}
