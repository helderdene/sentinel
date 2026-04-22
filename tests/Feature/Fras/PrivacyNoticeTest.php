<?php

use League\CommonMark\GithubFlavoredMarkdownConverter;

pest()->group('fras');

it('returns 200 for unauthenticated GET /privacy', function () {
    $this->get('/privacy')->assertOk();
});

it('renders the Privacy Inertia page with English content by default', function () {
    $this->get('/privacy')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Privacy')
            ->where('currentLang', 'en')
            ->has('availableLangs')
            ->has('content')
            ->where('content', fn (string $html) => str_contains($html, 'Privacy Notice'))
        );
});

it('renders Filipino content when ?lang=tl is passed', function () {
    $this->get('/privacy?lang=tl')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Privacy')
            ->where('currentLang', 'tl')
            ->where('content', fn (string $html) => str_contains($html, 'Paunawa sa Privacy'))
        );
});

it('falls back to English content on unknown lang values', function () {
    $this->get('/privacy?lang=xx')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('Privacy')
            ->where('currentLang', 'en')
            ->where('content', fn (string $html) => str_contains($html, 'Privacy Notice'))
        );
});

it('blocks path-traversal attempts via ?lang and returns English content', function () {
    // Attempting to escape resource_path via ?lang — controller must reject
    // any non-whitelist value and render en content from the safe path.
    $response = $this->get('/privacy?lang=../etc/passwd');

    $response->assertOk();
    $response->assertInertia(fn ($page) => $page
        ->component('Privacy')
        ->where('currentLang', 'en')
        ->where('content', fn (string $html) => str_contains($html, 'Privacy Notice')
            && ! str_contains($html, 'root:')
        )
    );
});

it('strips raw HTML injected into Markdown via the GithubFlavoredMarkdownConverter (html_input=strip)', function () {
    // Programmatic XSS fixture: feed a <script> + <img onerror> payload directly
    // through the exact converter shape the controller uses. Asserts html_input:strip
    // removes dangerous tags. No live privacy-notice.md edit required — this test
    // covers the sanitization contract of the compilation primitive itself.
    $malicious = <<<'MD'
# Evil

<script>alert('xss')</script>

<img src=x onerror="alert('xss')">

Normal body text.
MD;

    $converter = new GithubFlavoredMarkdownConverter(['html_input' => 'strip']);
    $html = (string) $converter->convert($malicious);

    expect($html)->not->toContain('<script>');
    expect($html)->not->toContain("alert('xss')");
    expect($html)->not->toContain('onerror');
    expect($html)->toContain('Normal body text');
});
