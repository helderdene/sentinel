<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use League\CommonMark\GithubFlavoredMarkdownConverter;

final class PrivacyNoticeController extends Controller
{
    /**
     * Render the public Privacy Notice page.
     *
     * Phase 22 D-30..D-32: unauthenticated citizen-facing surface exposing the
     * DPA-mandated Privacy Notice. Content is sourced from Git-tracked Markdown
     * files (`resources/privacy/privacy-notice.md` + `.tl.md`) and compiled at
     * request time via league/commonmark with `html_input => 'strip'` so any
     * inline HTML that slips into the source via PR is neutralised before it
     * reaches `v-html` on the Vue side (T-22-08-03 XSS mitigation).
     *
     * The `?lang` query param is whitelisted to ['en', 'tl']; any other value
     * (including path-traversal attempts like `../etc/passwd`) falls back to
     * 'en' — the controller NEVER concatenates arbitrary user input into the
     * file path (T-22-08-04 path-traversal mitigation).
     */
    public function show(Request $request): Response
    {
        $lang = in_array($request->query('lang'), ['en', 'tl'], true)
            ? $request->query('lang')
            : 'en';

        $path = resource_path('privacy/privacy-notice'.($lang === 'tl' ? '.tl' : '').'.md');
        $markdown = is_file($path) ? (string) file_get_contents($path) : '';

        $html = (new GithubFlavoredMarkdownConverter(['html_input' => 'strip']))
            ->convert($markdown);

        return Inertia::render('Privacy', [
            'content' => (string) $html,
            'availableLangs' => ['en', 'tl'],
            'currentLang' => $lang,
        ]);
    }
}
