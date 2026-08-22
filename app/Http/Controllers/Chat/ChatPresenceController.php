<?php

namespace App\Http\Controllers\Chat;

use App\Http\Controllers\Controller;
use App\Models\ChatAgentPresence;
use App\Services\Chat\ChatPresenceService;
use App\Support\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * An agent's own availability, plus the heartbeat that proves they are still
 * there. Every agent may set their own status; no permission beyond being able
 * to work the inbox is required.
 */
class ChatPresenceController extends Controller
{
    public function __construct(
        private readonly ChatPresenceService $presence,
        private readonly CurrentOrganization $currentOrganization,
    ) {}

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => 'required|in:'.implode(',', ChatAgentPresence::STATUSES),
        ]);

        $this->presence->setStatus($request->user(), $data['status']);

        return response()->json([
            'status' => $data['status'],
            'roster' => $this->presence->roster($this->currentOrganization->get()),
        ]);
    }

    /** Called on a timer by an open inbox; keeps the chosen status alive. */
    public function heartbeat(Request $request): JsonResponse
    {
        $this->presence->heartbeat($request->user());

        return response()->json([
            'status' => $this->presence->statusFor($request->user()),
            'roster' => $this->presence->roster($this->currentOrganization->get()),
        ]);
    }
}
