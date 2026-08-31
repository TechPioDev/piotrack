<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

/**
 * sitemap.xml for the public product pages (MSITE). Tenant surfaces (forms,
 * landing pages, booking) are deliberately excluded — they belong to customers.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $paths = ['/', '/features', '/how-it-works', '/results', '/about', '/contact', '/faq'];

        $urls = implode("\n", array_map(
            fn (string $path): string => '  <url><loc>'.e(url($path)).'</loc></url>',
            $paths,
        ));

        $xml = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
{$urls}
</urlset>
XML;

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }
}
