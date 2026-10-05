<?php

namespace App\Http\Controllers;

use App\Modules\Experience\Enums\SceneCode;
use App\Modules\Experience\Queries\BuildScene;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;

/**
 * Office scenes. Each scene is rendered from SceneData built server-side.
 */
class OfficeController extends Controller
{
    public function __construct(private readonly BuildScene $buildScene) {}

    public function reception(): View
    {
        return $this->scene(SceneCode::Reception, 'office.reception');
    }

    public function knowledge(): View
    {
        return $this->scene(SceneCode::Knowledge, 'office.knowledge');
    }

    public function hr(): View
    {
        return $this->scene(SceneCode::Hr, 'office.hr');
    }

    public function it(): View
    {
        return $this->scene(SceneCode::It, 'office.it');
    }

    public function requests(): View
    {
        return $this->scene(SceneCode::Requests, 'office.requests');
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array{article_id?: ?int, ticket_public_id?: ?string}  $context
     */
    protected function scene(SceneCode $scene, string $view, array $data = [], array $context = []): View
    {
        return view($view, [
            'scene' => $this->buildScene->handle($scene, $context),
            'adminHref' => Gate::allows('admin.access') ? route('admin.dashboard') : null,
        ] + $data);
    }
}
