<?php

declare(strict_types=1);

/**
 * The chat widget logo: uploaded on the settings page, shown in the chat header
 * on the customer's website. The file is served to every visitor of that site,
 * so these tests pin what may be uploaded, who may upload it, and that it is
 * only ever served for a live widget.
 */

use App\Authorization\Role;
use App\Models\ChatWidget;
use App\Support\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');

    [$this->org, $this->owner] = makeOrganization('PioManage');
    subscribeOrganization($this->org, 'enterprise');

    app(CurrentOrganization::class)->set($this->org);
    $this->widget = ChatWidget::create(['name' => 'PioManage website', 'status' => 'active']);
    app(CurrentOrganization::class)->forget();
});

function uploadLogo($test, UploadedFile $file, $user = null)
{
    return $test->actingAs($user ?? $test->owner)
        ->from(route('chat.widgets.edit', $test->widget))
        ->post(route('chat.widgets.logo.store', $test->widget), ['logo' => $file]);
}

it('stores an uploaded logo and hands its address to the settings page and the widget', function () {
    uploadLogo($this, UploadedFile::fake()->image('logo.png', 200, 200))
        ->assertRedirect(route('chat.widgets.edit', $this->widget))
        ->assertSessionHas('status', 'Logo uploaded.');

    $widget = $this->widget->refresh();
    Storage::disk('local')->assertExists($widget->logo_path);
    expect($widget->logo_path)->toStartWith("org-{$this->org->id}/chat-logos/");

    $this->actingAs($this->owner)->get(route('chat.widgets.edit', $widget))
        ->assertInertia(fn ($page) => $page->where('widget.logo_url', $widget->logoUrl()));

    expect($this->getJson(route('public.chat.config', $widget->public_key))->json('theme.logo_url'))->toBe($widget->logoUrl());
});

it('serves the logo to the website with a long cache, because every upload gets a new address', function () {
    uploadLogo($this, UploadedFile::fake()->image('logo.png', 120, 120));
    $widget = $this->widget->refresh();

    $response = $this->get($widget->logoUrl())->assertOk();

    expect($response->headers->get('Content-Type'))->toStartWith('image/png')
        ->and($response->headers->get('Cache-Control'))->toContain('max-age=604800')
        ->and($response->headers->get('X-Content-Type-Options'))->toBe('nosniff');
});

it('replaces the old file when a new logo is uploaded, and gives it a new address', function () {
    uploadLogo($this, UploadedFile::fake()->image('first.png', 100, 100));
    $first = $this->widget->refresh();
    [$firstPath, $firstUrl] = [$first->logo_path, $first->logoUrl()];

    uploadLogo($this, UploadedFile::fake()->image('second.jpg', 100, 100));
    $second = $this->widget->refresh();

    Storage::disk('local')->assertMissing($firstPath);
    Storage::disk('local')->assertExists($second->logo_path);
    expect($second->logoUrl())->not->toBe($firstUrl);
});

it('keeps the logo when the rest of the settings are saved', function () {
    uploadLogo($this, UploadedFile::fake()->image('logo.png', 100, 100));
    $path = $this->widget->refresh()->logo_path;

    $this->actingAs($this->owner)->patch(route('chat.widgets.update', $this->widget), [
        'theme' => ['title' => 'Chat with us', 'company' => 'PioManage', 'accent' => '#d57f07', 'position' => 'bottom-right'],
    ])->assertSessionHasNoErrors();

    expect($this->widget->refresh()->logo_path)->toBe($path);
});

it('refuses SVG, files that only pretend to be images, and oversized logos', function () {
    uploadLogo($this, UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'))
        ->assertSessionHasErrors(['logo' => 'The logo must be a PNG, JPG or WebP image.']);

    uploadLogo($this, UploadedFile::fake()->createWithContent('logo.png', 'MZ this is a Windows executable, not a picture'))
        ->assertSessionHasErrors('logo');

    uploadLogo($this, UploadedFile::fake()->image('huge.png', 100, 100)->size(900))
        ->assertSessionHasErrors(['logo' => 'The logo must be 512 KB or smaller.']);

    expect($this->widget->refresh()->logo_path)->toBeNull();
});

it('removes the logo, its file, and its public address', function () {
    uploadLogo($this, UploadedFile::fake()->image('logo.png', 100, 100));
    $widget = $this->widget->refresh();
    [$path, $url] = [$widget->logo_path, $widget->logoUrl()];

    $this->actingAs($this->owner)->delete(route('chat.widgets.logo.destroy', $widget))
        ->assertSessionHas('status', 'Logo removed.');

    Storage::disk('local')->assertMissing($path);
    expect($widget->refresh()->logo_path)->toBeNull();
    $this->get($url)->assertNotFound();
});

it('serves nothing for a widget that is not live', function () {
    uploadLogo($this, UploadedFile::fake()->image('logo.png', 100, 100));
    $widget = $this->widget->refresh();
    $url = $widget->logoUrl();

    $widget->forceFill(['status' => 'paused'])->save();

    $this->get($url)->assertNotFound();
});

it('lets only this organization\'s widget managers change the logo', function () {
    $viewer = addMember($this->org, Role::Viewer);
    uploadLogo($this, UploadedFile::fake()->image('logo.png', 100, 100), $viewer)->assertForbidden();

    [, $stranger] = makeOrganization('Someone Else');
    uploadLogo($this, UploadedFile::fake()->image('logo.png', 100, 100), $stranger)->assertNotFound();

    expect($this->widget->refresh()->logo_path)->toBeNull();
});
