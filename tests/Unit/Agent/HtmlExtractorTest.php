<?php

namespace Tests\Unit;

use App\Services\Agent\Research\HtmlExtractor;
use App\Services\Agent\Research\UrlHelper;
use PHPUnit\Framework\TestCase;

class HtmlExtractorTest extends TestCase
{
    private function page(): string
    {
        return <<<'HTML'
<html><head><title> Acme Ltd &amp; Co </title>
<meta name="Description" content="We build logistics software">
<script>var x = "hidden@script.com";</script><style>.a{}</style></head>
<body>
<nav><a href="/about">About us</a><a href="contact.html">Contact</a><a href="https://other.com/x#frag">Ext</a><a href="#top">Top</a></nav>
<h1>Welcome</h1><p>Hiring</p><p>Laravel developers</p>
<a href="mailto:Sales@Acme.com?subject=Hi">Email sales</a>
<a href="tel:+44 20 7946 0000">Call</a>
<a href="https://wa.me/2348012345678?text=hello">WhatsApp</a>
<a href="https://api.whatsapp.com/send?phone=2349000000000&text=x">WA2</a>
<span class="__cf_email__" data-cfemail="a4c5c7c1e4c5c7c18ac7cbc9">[email protected]</span>
<p>Write to info@acme.com. or logo@2x.png and you@example.com</p>
<form action="/send"><input name="q"></form>
</body></html>
HTML;
    }

    public function testBasics(): void
    {
        $r = HtmlExtractor::extract($this->page(), 'https://www.acme.com/index.html');
        $this->assertSame('Acme Ltd & Co', $r['title']);
        $this->assertSame('We build logistics software', $r['description']);
        $this->assertTrue($r['has_form']);
        $this->assertStringContainsString('Hiring Laravel developers', $r['text']);
        $this->assertFalse(str_contains($r['text'], 'hidden@script.com'));
    }

    public function testContactsComeOnlyFromPublishedSources(): void
    {
        $r = HtmlExtractor::extract($this->page(), 'https://www.acme.com/index.html');
        $this->assertContains('sales@acme.com', $r['emails']);
        $this->assertContains('info@acme.com', $r['emails']);
        $this->assertFalse(in_array('logo@2x.png', $r['emails'], true));
        $this->assertFalse(in_array('you@example.com', $r['emails'], true));
        $this->assertFalse(in_array('hidden@script.com', $r['emails'], true));
        $this->assertContains('+442079460000', $r['phones']);
        $this->assertContains('+2348012345678', $r['whatsapp']);
        $this->assertContains('+2349000000000', $r['whatsapp']);
    }

    public function testCloudflareObfuscatedEmailIsDecoded(): void
    {
        // key 0xa4 ; decodes to "a@b.co"-style string; build one we know
        $email = 'hi@acme.com';
        $key = 0x5c;
        $hex = sprintf('%02x', $key);
        foreach (str_split($email) as $ch) {
            $hex .= sprintf('%02x', ord($ch) ^ $key);
        }
        $r = HtmlExtractor::extract('<html><body><span data-cfemail="'.$hex.'">[email protected]</span></body></html>', 'https://acme.com/');
        $this->assertContains('hi@acme.com', $r['emails']);
    }

    public function testLinksAreResolved(): void
    {
        $r = HtmlExtractor::extract($this->page(), 'https://www.acme.com/team/index.html');
        $urls = array_column($r['links'], 'url');
        $this->assertContains('https://www.acme.com/about', $urls);
        $this->assertContains('https://www.acme.com/team/contact.html', $urls);
        $this->assertContains('https://other.com/x', $urls);
        $this->assertFalse(in_array('#top', $urls, true));
    }

    public function testUrlHelper(): void
    {
        $this->assertSame('https://a.com/x/y', UrlHelper::resolve('https://a.com/x/z.html', 'y'));
        $this->assertSame('https://a.com/y', UrlHelper::resolve('https://a.com/x/z.html', '../y'));
        $this->assertSame('https://a.com/q?z=1', UrlHelper::resolve('https://a.com/x/', '/q?z=1'));
        $this->assertSame('https://b.com/p', UrlHelper::resolve('https://a.com/', '//b.com/p'));
        $this->assertNull(UrlHelper::resolve('https://a.com/', 'mailto:x@y.com'));
        $this->assertNull(UrlHelper::resolve('https://a.com/', 'javascript:void(0)'));
        $this->assertSame('acme.com', UrlHelper::host('https://WWW.Acme.com/x'));
        $this->assertTrue(UrlHelper::sameSite('https://www.acme.com/a', 'http://acme.com/b'));
    }

    public function testEmptyAndMalformedHtml(): void
    {
        $this->assertSame('', HtmlExtractor::extract('', 'https://a.com')['text']);
        $r = HtmlExtractor::extract('<div><p>unclosed <b>tags', 'https://a.com');
        $this->assertStringContainsString('unclosed tags', $r['text']);
    }
}
