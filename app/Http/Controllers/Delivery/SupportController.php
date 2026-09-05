<?php

namespace App\Http\Controllers\Delivery;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\File;
use App\Models\KbArticle;
use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Services\Delivery\TicketService;
use App\Validation\TenantExists;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class SupportController extends Controller
{
    public function __construct(private TicketService $tickets) {}

    public function index(): Response
    {
        // SUPP-002: documents attached to tickets, loaded once and keyed.
        $attachments = File::where('attachable_type', Ticket::class)
            ->get(['id', 'attachable_id', 'name', 'size'])
            ->groupBy('attachable_id');

        return Inertia::render('delivery/support', [
            'tickets' => Ticket::with('messages')->latest('id')->limit(100)->get()->map(fn (Ticket $t) => [
                'id' => $t->id,
                'subject' => $t->subject,
                'body' => $t->body,
                'status' => $t->status,
                'priority' => $t->priority,
                'category' => $t->category,
                'assignee_id' => $t->assignee_id,
                'resolved_at' => $t->resolved_at?->toIso8601String(),
                'messages' => $t->messages->map(fn (TicketMessage $m) => [
                    'id' => $m->id,
                    'body' => $m->body,
                    'is_internal' => $m->is_internal,
                    'created_at' => $m->created_at?->toIso8601String(),
                ])->all(),
                // SUPP-002: the ticket's attached documents.
                'attachments' => ($attachments->get($t->id) ?? collect())->map(fn (File $f) => [
                    'id' => $f->id, 'name' => $f->name, 'size' => $f->size,
                ])->values()->all(),
            ]),
            // The help centre is product-wide, not tenant data.
            'articles' => KbArticle::orderBy('title')->get()->map(fn (KbArticle $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'slug' => $a->slug,
                'category' => $a->category,
                'excerpt' => $a->excerpt,
                'is_published' => $a->is_published,
            ]),
            'announcements' => Announcement::whereNotNull('published_at')->latest('published_at')->limit(20)->get()
                ->map(fn (Announcement $a) => [
                    'id' => $a->id,
                    'title' => $a->title,
                    'body' => $a->body,
                    'type' => $a->type,
                    'published_at' => $a->published_at?->toIso8601String(),
                ]),
            'members' => User::query()->limit(100)->get(['id', 'name'])->map(fn (User $u) => ['id' => $u->id, 'name' => $u->name]),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->tickets->open($request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:5000'],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'category' => ['nullable', 'string', 'max:100'],
        ]), $request->user());

        return back()->with('status', __('Ticket created.'));
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['boolean'],
        ]);

        $this->tickets->reply($ticket, $data['body'], $request->user(), (bool) ($data['is_internal'] ?? false));

        return back()->with('status', __('Reply added.'));
    }

    public function assign(Request $request, Ticket $ticket): RedirectResponse
    {
        $data = $request->validate(['user_id' => ['required', 'integer', TenantExists::member()]]);
        $this->tickets->assign($ticket, User::findOrFail($data['user_id']));

        return back()->with('status', __('Ticket assigned.'));
    }

    public function resolve(Ticket $ticket): RedirectResponse
    {
        $this->tickets->resolve($ticket);

        return back()->with('status', __('Ticket resolved.'));
    }

    public function storeArticle(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:100'],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string'],
            'is_published' => ['boolean'],
        ]);

        KbArticle::create([
            'title' => $data['title'],
            'slug' => Str::slug($data['title']).'-'.Str::lower(Str::random(6)),
            'category' => $data['category'] ?? null,
            'excerpt' => $data['excerpt'] ?? null,
            'body' => $data['body'],
            'is_published' => (bool) ($data['is_published'] ?? false),
        ]);

        return back()->with('status', __('Article saved.'));
    }
}
