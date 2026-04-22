<?php

namespace App\Console\Commands;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Console\Command;
use InvalidArgumentException;
use League\CommonMark\GithubFlavoredMarkdownConverter;

class FrasDpaExport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'fras:dpa:export {--doc=all : pia|signage|training|all} {--lang=en : en|tl}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export DPA documentation (PIA, signage, operator training) to PDF via dompdf';

    /**
     * Execute the console command.
     *
     * Writes PDFs to storage/app/dpa-exports/{YYYY-MM-DD}/ — a directory that
     * is NOT symlinked to public/ (per T-22-09-03 mitigation). Markdown
     * content is sanitized by GithubFlavoredMarkdownConverter with
     * html_input: strip so dompdf receives only trusted HTML (T-22-09-01).
     */
    public function handle(): int
    {
        $doc = (string) $this->option('doc');
        $lang = (string) $this->option('lang');

        $docs = $doc === 'all' ? ['pia', 'signage', 'training'] : [$doc];

        $outDir = storage_path('app/dpa-exports/'.now()->format('Y-m-d'));
        if (! is_dir($outDir)) {
            mkdir($outDir, 0755, true);
        }

        $converter = new GithubFlavoredMarkdownConverter(['html_input' => 'strip']);

        foreach ($docs as $d) {
            $mdPath = $this->resolveMarkdownPath($d, $lang);

            if (! file_exists($mdPath)) {
                $this->error("Source markdown not found: {$mdPath}");

                return self::FAILURE;
            }

            $markdown = file_get_contents($mdPath);
            $html = (string) $converter->convert($markdown);

            $pdf = Pdf::loadView('dpa.export', [
                'content' => $html,
                'title' => $this->titleFor($d, $lang),
            ]);

            $outPath = "{$outDir}/{$d}-{$lang}.pdf";
            $pdf->save($outPath);

            $this->info($outPath);
        }

        return self::SUCCESS;
    }

    /**
     * Resolve the source Markdown file path for a given doc slug + lang code.
     */
    private function resolveMarkdownPath(string $doc, string $lang): string
    {
        return match ($doc) {
            'pia' => base_path('docs/dpa/PIA-template.md'),
            'signage' => base_path('docs/dpa/signage-template'.($lang === 'tl' ? '.tl' : '').'.md'),
            'training' => base_path('docs/dpa/operator-training.md'),
            default => throw new InvalidArgumentException("Unknown doc slug: {$doc}"),
        };
    }

    /**
     * Human-readable title injected into the PDF header.
     */
    private function titleFor(string $doc, string $lang): string
    {
        $map = [
            'pia' => 'Privacy Impact Assessment',
            'signage' => $lang === 'tl' ? 'Paunawa sa FRAS CCTV Zone' : 'FRAS CCTV Zone Notice',
            'training' => 'FRAS Operator Training',
        ];

        return $map[$doc] ?? ucfirst($doc);
    }
}
