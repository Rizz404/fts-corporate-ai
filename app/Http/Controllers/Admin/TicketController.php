<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTeam;
use App\Models\Ticket;
use App\Models\User;
use App\Modules\Identity\CurrentActor;
use App\Modules\Identity\Enums\RoleCode;
use App\Modules\Support\Enums\TicketStatus;
use App\Modules\Support\Support\TicketAccess;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Staff queue for the teams the user currently belongs to.
 */
class TicketController extends Controller
{
    public function __construct(private readonly CurrentActor $actors) {}

    public function index(Request $request): View
    {
        Gate::authorize('tickets.staff');
        $teamIds = TicketAccess::handledTeamIds($this->actors->for($request->user()));
        $teams = SupportTeam::query()->whereIn('id', $teamIds)->orderBy('code')->get(['id', 'code']);

        $teamFilter = $request->integer('team') ?: null;
        $teamFilter = in_array($teamFilter, $teamIds, true) ? $teamFilter : null;
        $status = in_array($request->query('status'), TicketStatus::values(), true) ? $request->query('status') : null;
        $mine = $request->boolean('mine');

        $tickets = Ticket::query()
            ->with(['team:id,code', 'assignee:id,name', 'requester:id,name'])
            ->whereIn('team_id', $teamFilter ? [$teamFilter] : $teamIds)
            ->when($status, fn ($query) => $query->where('status', $status), fn ($query) => $query->where('status', '!=', TicketStatus::Closed->value))
            ->when($mine, fn ($query) => $query->where('assignee_id', $request->user()->id))
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.tickets.index', [
            'tickets' => $tickets,
            'teamOptions' => $teams->mapWithKeys(fn ($team) => [$team->id => __('ui.teams.'.$team->code->value)])->all(),
            'statusOptions' => collect(TicketStatus::cases())->mapWithKeys(fn (TicketStatus $s) => [$s->value => $s->label()])->all(),
            'filters' => ['team' => $teamFilter, 'status' => $status, 'mine' => $mine],
        ]);
    }

    public function show(Request $request, Ticket $ticket): View
    {
        Gate::authorize('tickets.staff');
        abort_unless(TicketAccess::canHandle($this->actors->for($request->user()), $ticket), 404);

        $ticket->load(['team:id,code', 'assignee:id,name', 'requester:id,name,email', 'events.actor:id,name']);

        $assignees = User::query()
            ->active()
            ->withRole(RoleCode::Staff)
            ->whereHas('supportTeams', fn ($query) => $query->whereKey($ticket->team_id))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();

        return view('admin.tickets.show', [
            'ticket' => $ticket,
            'assigneeOptions' => $assignees,
        ]);
    }
}
