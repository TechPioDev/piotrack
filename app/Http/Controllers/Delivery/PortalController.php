<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Deliverable;
use App\Services\Delivery\PortalService;
use App\Services\Delivery\ProjectService;
use App\Services\Delivery\TicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * The client portal (PORTAL). Everything served here comes from PortalService,
 * which narrows to client-visible records only.
 */
class PortalController extends Controller
{
    public function __construct(
        private PortalService $portal,
        private ProjectService $projects,
        private TicketService $tickets,
    ) {}

    public function dashboard(): Response
    {
        return Inertia::render('portal/dashboard', [
            'metrics' => $this->portal->dashboard(),
            'projects' => $this->portal->projects(),
            'deliverables' => $this->portal->deliverables(),
            // PORTAL-003/014/015: campaign status, meeting notes, roadmap.
            'campaigns' => $this->portal->campaigns(),
            'roadmap' => $this->portal->roadmap(),
            'meeting_notes' => $this->portal->meetingNotes(),
        ]);
    }

    public function projects(): Response
    {
        return Inertia::render('portal/projects', [
            'projects' => $this->portal->projects(),
            'tasks' => $this->portal->tasks(),
            'deliverables' => $this->portal->deliverables(),
        ]);
    }

    public function support(): Response
    {
        return Inertia::render('portal/support', [
            'tickets' => $this->portal->ticketsForClient(),
            'files' => $this->portal->files(),
        ]);
    }

    /**
     * Approve a deliverable. A client may only act on deliverables that were
     * made visible to them — anything else is not found, not forbidden, so the
     * portal never reveals that a hidden deliverable exists.
     */
    public function approve(Request $request, Deliverable $deliverable): RedirectResponse
    {
        $this->assertVisible($deliverable);

        $user = $request->user();
        abort_if($user === null, 403);

        try {
            $this->projects->approve($deliverable, $user);
        } catch (RuntimeException $e) {
            return back()->withErrors(['deliverable' => $e->getMessage()]);
        }

        return back()->with('status', __('Deliverable approved.'));
    }

    public function reject(Request $request, Deliverable $deliverable): RedirectResponse
    {
        $this->assertVisible($deliverable);

        $data = $request->validate(['reason' => ['required', 'string', 'max:1000']]);
        $user = $request->user();
        abort_if($user === null, 403);

        try {
            $this->projects->reject($deliverable, $user, $data['reason']);
        } catch (RuntimeException $e) {
            return back()->withErrors(['deliverable' => $e->getMessage()]);
        }

        return back()->with('status', __('Feedback sent.'));
    }

    /**
     * Raise a support request from the portal (PORTAL-010/011).
     */
    public function openTicket(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
        ]);

        $this->tickets->open($data, $request->user());

        return back()->with('status', __('Support request submitted.'));
    }

    private function assertVisible(Deliverable $deliverable): void
    {
        if (! $deliverable->client_visible) {
            throw new NotFoundHttpException;
        }
    }
}
