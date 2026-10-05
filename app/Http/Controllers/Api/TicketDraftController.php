<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ConfirmTicketDraftRequest;
use App\Http\Requests\Api\TicketDraftRequest;
use App\Models\TicketDraft;
use App\Modules\Support\Actions\ConfirmTicketDraft;
use App\Modules\Support\Actions\PrepareTicketDraft;
use App\Modules\Support\Actions\UpdateTicketDraft;
use App\Modules\Support\Presenters\TicketPresenter;
use App\Support\Http\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Drafts belong to the session user; other users' drafts are 404.
 */
class TicketDraftController extends Controller
{
    public function store(TicketDraftRequest $request, PrepareTicketDraft $prepare): JsonResponse
    {
        $draft = $prepare->handle($request->user(), $request->draftData(), $request->validated('conversation_public_id'));

        return ApiResponse::success(TicketPresenter::draft($draft), status: 201);
    }

    public function show(Request $request, TicketDraft $draft): JsonResponse
    {
        abort_unless($draft->requester_id === $request->user()->id, 404);

        return ApiResponse::success(TicketPresenter::draft($draft));
    }

    public function update(TicketDraftRequest $request, TicketDraft $draft, UpdateTicketDraft $update): JsonResponse
    {
        $draft = $update->handle($request->user(), $draft, (int) $request->validated('expected_revision'), $request->draftData());

        return ApiResponse::success(TicketPresenter::draft($draft));
    }

    public function confirm(ConfirmTicketDraftRequest $request, TicketDraft $draft, ConfirmTicketDraft $confirm): JsonResponse
    {
        abort_unless($draft->requester_id === $request->user()->id, 404);

        $result = $confirm->handle(
            $request->user(),
            $draft,
            (int) $request->validated('expected_revision'),
            $request->validated('idempotency_key'),
        );

        return ApiResponse::success(TicketPresenter::ticket($result['ticket']), status: $result['created'] ? 201 : 200);
    }
}
