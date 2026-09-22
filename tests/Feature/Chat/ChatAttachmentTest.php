<?php

declare(strict_types=1);

/**
 * Visitors can send pictures in the chat - a screenshot of an error, a photo
 * of the faulty kit. Pictures only: no documents, text, PDFs or video. They
 * come from anonymous people on the internet, so these tests pin what may be
 * sent, that it is checked before it is kept, and that only the visitor and
 * the widget's own team can see it.
 */

use App\Authorization\Role;
use App\Models\ChatConversation;
use App\Models\ChatMessage;
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

function aPicture(string $name = 'screenshot.png'): UploadedFile
{
    return UploadedFile::fake()->image($name, 120, 80);
}

function chatFor(string $token): ChatConversation
{
    return ChatConversation::withoutGlobalScope('tenant')->firstWhere('token', $token);
}

it('keeps a picture the visitor sends, privately, as part of the conversation', function () {
    $response = sendFile($this, aPicture('error.png'))
        ->assertOk()
        ->assertJsonPath('message.body', '📎 error.png');

    $conversation = chatFor($this->token);
    $message = $conversation->messages()->where('role', 'visitor')->sole();
    $path = $message->meta['attachment']['path'];

    Storage::disk('local')->assertExists($path);
    expect($path)->toStartWith("org-{$this->org->id}/chat-files/{$conversation->id}/")
        ->and($message->meta['attachment']['name'])->toBe('error.png')
        ->and($response->json('message.id'))->toBe($message->id)
        // A picture is not an answer: the visitor is still on the same question.
        ->and($conversation->answers['_node'])->toBe('q_service');
});

it('refuses anything that is not a picture: documents, text, PDFs, video', function (string $name, string $content) {
    sendFile($this, UploadedFile::fake()->createWithContent($name, $content))
        ->assertStatus(422)
        ->assertJsonPath('errors.file.0', 'You can send pictures only: PNG, JPG, GIF or WebP.');

    expect(chatFor($this->token)->messages()->where('role', 'visitor')->count())->toBe(0);
})->with([
    'a PDF' => ['invoice.pdf', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj"],
    'a text file' => ['notes.txt', 'Printer on floor 2 is offline'],
    'a spreadsheet' => ['figures.csv', "seat,count\nm365,42"],
    'a Word document' => ['report.docx', "PK\x03\x04".str_repeat("\x00", 26).'word/document.xml'],
    'a video' => ['clip.mp4', "\x00\x00\x00\x18ftypmp42\x00\x00\x00\x00mp42isom".str_repeat("\x00", 64)],
]);

it('reads the type from what is in the file, not from its name', function () {
    // A real upload (not a test fake, which takes its type from the name):
    // a PDF renamed to .png is refused as not a picture.
    $path = storage_path('framework/chat-renamed-'.uniqid());
    file_put_contents($path, "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj");

    sendFile($this, new UploadedFile($path, 'looks-like.png', null, null, true))
        ->assertStatus(422)
        ->assertJsonPath('errors.file.0', 'You can send pictures only: PNG, JPG, GIF or WebP.');

    @unlink($path);
});

it('refuses a picture given a document\'s name', function () {
    sendFile($this, UploadedFile::fake()->image('actually-a-picture.pdf', 50, 50))
        ->assertStatus(422)
        ->assertJsonPath('errors.file.0', 'You can send pictures only: PNG, JPG, GIF or WebP.');
});

it('refuses pictures that are too big, and other files dressed up as pictures', function () {
    sendFile($this, aPicture('huge.png')->size(6000))
        ->assertStatus(422)->assertJsonPath('errors.file.0', 'Pictures can be up to 5 MB.');

    // Refused whichever check sees it first: the type read from the bytes, or
    // the content scanner (test fakes report their type from the name).
    sendFile($this, UploadedFile::fake()->createWithContent('photo.jpg', 'MZ a Windows program pretending to be a photo'))
        ->assertStatus(422);
    sendFile($this, UploadedFile::fake()->createWithContent('looks-like.png', "%PDF-1.4\n1 0 obj << /Type /Catalog >> endobj"))
        ->assertStatus(422);

    expect(chatFor($this->token)->messages()->where('role', 'visitor')->count())->toBe(0);
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

    // The plain link downloads it, for saving.
    $saved = $this->actingAs($this->owner)->get(route('chat.conversations.files.show', [$conversation, $message]))->assertOk();
    expect($saved->headers->get('Content-Disposition'))->toContain('attachment')->toContain('screenshot.png');
});

it('only ever downloads a file kept from before chats took pictures only', function () {
    // Documents sent while other files were allowed stay readable by the team,
    // but are never rendered in the browser.
    Storage::disk('local')->put('legacy/notes.txt', 'plain text');
    $conversation = chatFor($this->token);
    app(CurrentOrganization::class)->set($this->org);
    $message = ChatMessage::create([
        'chat_conversation_id' => $conversation->id,
        'role' => 'visitor',
        'body' => '📎 notes.txt',
        'meta' => ['attachment' => ['path' => 'legacy/notes.txt', 'name' => 'notes.txt', 'size' => 10, 'mime' => 'text/plain']],
    ]);
    app(CurrentOrganization::class)->forget();

    $this->actingAs($this->owner)->get(route('chat.conversations.show', $conversation))
        ->assertInertia(fn ($page) => $page->where('messages', fn ($messages) => collect($messages)->firstWhere('id', $message->id)['attachment']['preview_url'] === null));

    $asked = $this->actingAs($this->owner)->get(route('chat.conversations.files.show', [$conversation, $message, 'inline' => 1]))->assertOk();
    expect($asked->headers->get('Content-Disposition'))->toStartWith('attachment');

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

it('keeps each visitor\'s pictures to their own conversation', function () {
    $url = sendFile($this, aPicture('private.png'))->json('message.attachment.url');

    // Another conversation's token, or a made-up one, gets nothing.
    $otherToken = $this->postJson("/wc/{$this->key}/conversations")->json('token');
    $this->get(str_replace($this->token, $otherToken, $url))->assertNotFound();
    $this->get(str_replace($this->token, 'cv_'.str_repeat('x', 32), $url))->assertNotFound();

    // Nor once the widget is no longer live.
    $this->widget->forceFill(['status' => 'paused'])->save();
    $this->get($url)->assertNotFound();
});

it('keeps its own rate limit, so a chat that has been polling can still send a picture', function () {
    foreach (range(1, 15) as $i) {
        $this->getJson("/wc/{$this->key}/conversations/{$this->token}/poll?since=0")->assertOk();
    }

    sendFile($this, aPicture('after-polling.png'))->assertOk();
});

it('stops at ten pictures per conversation', function () {
    // The per-minute limit is its own safeguard; this is about the total.
    $this->withoutMiddleware(ThrottleRequests::class);

    foreach (range(1, 10) as $i) {
        sendFile($this, aPicture("shot-{$i}.png"))->assertOk();
    }

    sendFile($this, aPicture('shot-11.png'))
        ->assertStatus(422)->assertJsonPath('errors.file.0', 'This chat already has 10 pictures. Please email anything else to the team.');
});

it('takes nothing before the visitor has agreed to the privacy question', function () {
    app(CurrentOrganization::class)->set($this->org);
    $this->widget->update(['consent' => ['required' => true]]);
    app(CurrentOrganization::class)->forget();
    $token = $this->postJson("/wc/{$this->key}/conversations")->assertJsonPath('node.type', 'consent')->json('token');

    sendFile($this, aPicture(), $token)
        ->assertStatus(422)->assertJsonPath('errors.file.0', 'Please answer the privacy question before sending a picture.');

    Storage::disk('local')->assertDirectoryEmpty('/');
});

it('takes nothing when the widget has pictures turned off, or the chat has ended', function () {
    app(CurrentOrganization::class)->set($this->org);
    $this->widget->update(['settings' => ['attachments' => false]]);
    app(CurrentOrganization::class)->forget();
    sendFile($this, aPicture())->assertNotFound();

    app(CurrentOrganization::class)->set($this->org);
    $this->widget->update(['settings' => ['attachments' => true]]);
    app(CurrentOrganization::class)->forget();
    chatFor($this->token)->forceFill(['status' => 'closed'])->save();
    sendFile($this, aPicture())->assertStatus(410);
});

it('lets only this organization\'s chat team open the picture', function () {
    sendFile($this, aPicture('private.png'))->assertOk();
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
