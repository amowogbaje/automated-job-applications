<?php

namespace Tests\Unit;

use App\Services\Translation\LanguageDetector;
use App\Services\Translation\TextChunker;
use PHPUnit\Framework\TestCase;

class TranslationTest extends TestCase
{
    public function test_german_job_ad_is_detected(): void
    {
        $de = 'Wir suchen einen erfahrenen PHP Entwickler (m/w/d) für unser Team in Berlin. Du bist verantwortlich für die Entwicklung und Wartung unserer Laravel Anwendungen und arbeitest eng mit dem Produktteam zusammen. Wir bieten dir ein attraktives Gehalt und flexible Arbeitszeiten.';
        $this->assertSame('de', (new LanguageDetector())->detect($de));
    }

    public function test_german_ad_with_mostly_english_tech_terms_is_still_detected(): void
    {
        $de = 'Backend Developer (m/w/d) Laravel Vue.js. Du entwickelst mit uns neue Features und arbeitest im Team an der Weiterentwicklung unserer Plattform. Wir bieten dir Homeoffice und die Möglichkeit, dich weiterzubilden.';
        $this->assertSame('de', (new LanguageDetector())->detect($de));
    }

    public function test_french_is_detected(): void
    {
        $fr = 'Nous recherchons un développeur PHP pour rejoindre notre équipe à Paris. Vous travaillerez avec les équipes produit sur des projets Laravel et vous serez responsable de la qualité du code dans une entreprise en pleine croissance.';
        $this->assertSame('fr', (new LanguageDetector())->detect($fr));
    }

    public function test_english_is_left_alone(): void
    {
        $en = 'We are looking for a senior Laravel developer to join our remote team. You will work with the product team and have experience with PHP, Vue and PostgreSQL. This is a full-time role.';
        $this->assertSame('en', (new LanguageDetector())->detect($en));
        $this->assertSame('en', (new LanguageDetector())->detect('Laravel Developer'));
        $this->assertSame('en', (new LanguageDetector())->detect(''));
    }

    public function test_chunker_respects_byte_limit_and_is_lossless(): void
    {
        $text = str_repeat('Wir suchen einen Entwickler für unser Team in München. ', 30) . "\n\n" . str_repeat('Über uns: schöne Büros, Äpfel und Öl. ', 25);
        $chunks = TextChunker::chunk($text, 450);

        foreach ($chunks as $c) {
            $this->assertTrue(strlen($c) <= 450);
            $this->assertTrue(mb_check_encoding($c, 'UTF-8'));
        }
        $this->assertSame(trim($text), trim(implode('', $chunks)));
    }

    public function test_chunker_splits_one_giant_word_without_breaking_utf8(): void
    {
        $text = str_repeat('ä', 1000);
        foreach (TextChunker::chunk($text, 450) as $c) {
            $this->assertTrue(strlen($c) <= 450);
            $this->assertTrue(mb_check_encoding($c, 'UTF-8'));
        }
        $this->assertSame(1, count(TextChunker::chunk('short', 450)));
    }
}
