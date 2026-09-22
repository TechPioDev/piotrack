<?php

declare(strict_types=1);

/**
 * Visitors can send files in the chat - a screenshot of an error, a document
 * the team asked for. The files come from anonymous people on the internet,
 * so these tests pin what may be sent, that it is checked before it is kept,
 * and that only the widget's own team can open it.
 */

use App\Authorization\Role;
use App\Models\ChatConversation;
use App\Models\ChatWidget;
use App\Support\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'PioManage website', 'status' => 'active']);
    app(CurrentOrganization::class)->forget();

    $this->key = $this->widget->public_key;
    $this->token = $this->postJson("/wc/{$this->key}/conversations")->assertOk()->json('token');
});

function sendFile($test, UploadedFile $file, ?string $token = null)
{
    return $test->post("/wc/{$test->key}/conversations/".($token ?? $test->token).'/files', ['file' => $file], ['Accept' => 'application/json']);
}

function chatFor(string $token): ChatConversation
{
    return ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $token);
}

it('keeps a file the visitor sends, privately, as part of the conversation', function () {
    $response = sendFile($this, UploadedFile::fake()->createWithContent('error.pdf', "%PDF-1.4\nscreenshot of the error"))
        ->assertOk()
        ->assertJsonPath('message.body', '📎 error.pdf');

    $conversation = chatFor($this->token);
    $message = $conversation->messages()->where('role', 'visitor')->sole();
    $path = $message->meta['attachment']['path'];

    Storage::disk('local')->assertExists($path);
    expect($path)->toStartWith("org-{$this->org->id}/chat-files/{$conversation->id}/")
        ->and($message->meta['attachment']['name'])->toBe('error.pdf')
        ->and($response->json('message.id'))->toBe($message->id)
        // A file is not an answer: the visitor is still on the same question.
        ->and($conversation->answers['_node'])->toBe('q_service');
});

it('lets the team download it from the conversation', function () {
    sendFile($this, UploadedFile::fake()->createWithContent('notes.txt', 'Printer on floor 2 is offline'))->assertOk();
    $conversation = chatFor($this->token);
    $message = $conversation->messages()->where('role', 'visitor')->sole();

    $this->actingAs($this->owner)->get(route('chat.conversations.show', $conversation))
        ->assertInertia(fn ($page) => $page->where('messages', fn ($messages) => collect($messages)->firstWhere('id', $message->id)['attachment']['name'] === 'notes.txt'));

    $download = $this->actingAs($this->owner)->get(route('chat.conversations.files.show', [$conversation, $message]))->assertOk();
    expect($download->headers->get('Content-Disposition'))->toContain('attachment')->toContain('notes.txt')
        ->and($download->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('accepts an ordinary photo whose compressed bytes happen to contain "<%"', function () {
    // One photo in four carries those two bytes by chance, and used to be
    // refused as "embedded script content".
    $photo = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00".random_bytes(3000).'<%'.random_bytes(3000);

    sendFile($this, UploadedFile::fake()->createWithContent('holiday.jpg', $photo))->assertOk();
});

it('shows a picture in the team\'s transcript and serves it as a picture', function () {
    sendFile($this, UploadedFile::fake()->image('screenshot.png', 320, 200))->assertOk();
    $conversation = chatFor($this->token);
    $message = $conversation->messages()->where('role', 'visitor')->sole();
    $preview = route('chat.conversations.files.show', [$conversation, $message, 'inline' => 1]);

    $this->actingAs($this->owner)->get(route('chat.conversations.show', $conversation))
        ->assertInertia(fn ($page) => $page->where('messages', fn ($messages) => collect($messages)->firstWhere('id', $message->id)['attachment']['preview_url'] === $preview));

    $shown = $this->actingAs($this->owner)->get($preview)->assertOk();
    expect($shown->headers->get('Content-Type'))->toBe('image/png')
        ->and($shown->headers->get('Content-Disposition'))->toStartWith('inline')
        ->and($shown->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($shown->headers->get('Content-Security-Policy'))->toContain('sandbox');
});

it('never shows anything but a picture inline, even when asked to', function () {
    sendFile($this, UploadedFile::fake()->createWithContent('notes.txt', 'plain text'))->assertOk();
    $conversation = chatFor($this->token);
    $message = $conversation->messages()->where('role', 'visitor')->sole();

    $this->actingAs($this->owner)->get(route('chat.conversations.show', $conversation))
        ->assertInertia(fn ($page) => $page->where('messages', fn ($messages) => collect($messages)->firstWhere('id', $message->id)['attachment']['preview_url'] === null));

    $asked = $this->actingAs($this->owner)->get(route('chat.conversations.files.show', [$conversation, $message, 'inline' => 1]))->assertOk();
    expect($asked->headers->get('Content-Disposition'))->toStartWith('attachment');

    // The visitor's own link downloads it too, rather than rendering it.
    $visitorCopy = $this->get(route('public.chat.file', ['publicKey' => $this->key, 'token' => $this->token, 'message' => $message->id]))->assertOk();
    expect($visitorCopy->headers->get('Content-Disposition'))->toStartWith('attachment');
});

it('lets the visitor see their own picture in the chat, again after reopening it', function () {
    $sent = sendFile($this, UploadedFile::fake()->image('error.png', 300, 180))->assertOk();
    $url = $sent->json('message.attachment.url');
    expect($sent->json('message.attachment'))->toMatchArray(['name' => 'error.png', 'image' => true]);

    $picture = $this->get($url)->assertOk();
    expect($picture->headers->get('Content-Type'))->toBe('image/png')
        ->and($picture->headers->get('Content-Disposition'))->toStartWith('inline');

    // Reopening the chat replays it as a picture, not as a file name.
    $replayed = collect($this->getJson("/wc/{$this->key}/conversations/{$this->token}/poll?since=0&transcript=1")->json('messages'))
        ->firstWhere('role', 'visitor');
    expect($replayed['attachment'])->toBe(['name' => 'error.png', 'image' => true, 'url' => $url]);
});

it('keeps each visitor\'s files to their own conversation', function () {
    $url = sendFile($this, UploadedFile::fake()->image('private.png', 100, 100))->json('message.attachment.url');
    $message = chatFor($this->token)->messages()->where('role', 'visitor')->sole();

    // Another conversation's token, or a made-up one, gets nothing.
    $otherToken = $this->postJson("/wc/{$this->key}/conversations")->json('token');
    $this->get(str_replace($this->token, $otherToken, $url))->assertNotFound();
    $this->get(str_replace($this->token, 'cv_'.str_repeat('x', 32), $url))->assertNotFound();

    // Nor once the widget is no longer live.
    $this->widget->forceFill(['status' => 'paused'])->save();
    $this->get($url)->assertNotFound();
    expect($message->exists)->toBeTrue();
});

it('refuses files that are too big, of the wrong kind, or not what they claim to be', function () {
    sendFile($this, UploadedFile::fake()->create('huge.pdf', 6000, 'application/pdf'))
        ->assertStatus(422)->assertJsonPath('errors.file.0', 'Files can be up to 5 MB.');

    sendFile($this, UploadedFile::fake()->createWithContent('setup.exe', 'MZ a Windows program'))
        ->assertStatus(422);

    sendFile($this, UploadedFile::fake()->createWithContent('invoice.pdf', 'MZ a Windows program pretending to be a PDF'))
        ->assertStatus(422);

    expect(chatFor($this->token)->messages()->where('role', 'visitor')->count())->toBe(0);
});

it('keeps its own rate limit, so a chat that has been polling can still send a file', function () {
    foreach (range(1, 15) as $i) {
        $this->getJson("/wc/{$this->key}/conversations/{$this->token}/poll?since=0")->assertOk();
    }

    sendFile($this, UploadedFile::fake()->createWithContent('after-polling.txt', 'still allowed'))->assertOk();
});

it('stops at ten files per conversation', function () {
    // The per-minute limit is its own safeguard; this is about the total.
    $this->withoutMiddleware(ThrottleRequests::class);

    foreach (range(1, 10) as $i) {
        sendFile($this, UploadedFile::fake()->createWithContent("note-{$i}.txt", "note {$i}"))->assertOk();
    }

    sendFile($this, UploadedFile::fake()->createWithContent('note-11.txt', 'one too many'))
        ->assertStatus(422)->assertJsonPath('errors.file.0', 'This chat already has 10 files. Please email anything else to the team.');
});

it('takes nothing before the visitor has agreed to the privacy question', function () {
    app(CurrentOrganization::class)->set($this->org);
    $this->widget->update(['consent' => ['required' => true]]);
    app(CurrentOrganization::class)->forget();
    $token = $this->postJson("/wc/{$this->key}/conversations")->assertJsonPath('node.type', 'consent')->json('token');

    sendFile($this, UploadedFile::fake()->createWithContent('note.txt', 'hello'), $token)
        ->assertStatus(422)->assertJsonPath('errors.file.0', 'Please answer the privacy question before sending a file.');

    Storage::disk('local')->assertDirectoryEmpty('/');
});

it('takes nothing when the widget has files turned off, or the chat has ended', function () {
    app(CurrentOrganization::class)->set($this->org);
    $this->widget->update(['settings' => ['attachments' => false]]);
    app(CurrentOrganization::class)->forget();
    sendFile($this, UploadedFile::fake()->createWithContent('note.txt', 'hello'))->assertNotFound();

    app(CurrentOrganization::class)->set($this->org);
    $this->widget->update(['settings' => ['attachments' => true]]);
    app(CurrentOrganization::class)->forget();
    chatFor($this->token)->forceFill(['status' => 'closed'])->save();
    sendFile($this, UploadedFile::fake()->createWithContent('note.txt', 'hello'))->assertStatus(410);
});

it('lets only this organization\'s chat team open the file', function () {
    sendFile($this, UploadedFile::fake()->createWithContent('notes.txt', 'private'))->assertOk();
    $conversation = chatFor($this->token);
    $message = $conversation->messages()->where('role', 'visitor')->sole();
    $botLine = $conversation->messages()->where('role', 'bot')->firstOrFail();
    $other = chatFor($this->postJson("/wc/{$this->key}/conversations")->json('token'));
    $url = route('chat.conversations.files.show', [$conversation, $message]);

    [, $stranger] = makeOrganization('Someone Else');
    $this->actingAs($stranger)->get($url)->assertNotFound();

    // A message that is not a file, or belongs to another conversation, is not served.
    $this->actingAs($this->owner)->get(route('chat.conversations.files.show', [$conversation, $botLine]))->assertNotFound();
    $this->actingAs($this->owner)->get(route('chat.conversations.files.show', [$other, $message]))->assertNotFound();

    $viewer = addMember($this->org, Role::Viewer);
    $this->actingAs($viewer)->get($url)->assertOk();
});
