<?php

namespace App\Services\Translation;

/**
 * Cheap, offline language guess for job text (no API call, no quota). It only has
 * to answer "is this clearly NOT English, and which language?" so the translator
 * knows what to pick up. Uses stopword frequency; unsure => 'en' (leave it alone).
 */
class LanguageDetector
{
    private const STOPWORDS = [
        'en' => ['the', 'and', 'for', 'with', 'you', 'our', 'are', 'will', 'your', 'have', 'from', 'that', 'this', 'about', 'work', 'team', 'experience', 'we', 'is', 'to', 'of', 'in', 'a'],
        'de' => ['und', 'der', 'die', 'das', 'mit', 'für', 'fuer', 'wir', 'sie', 'ein', 'eine', 'einen', 'bei', 'von', 'zu', 'auf', 'ist', 'sind', 'oder', 'als', 'auch', 'bist', 'dein', 'deine', 'unser', 'unsere', 'uns', 'im', 'den', 'dem', 'des', 'nach', 'aus', 'werden', 'wird', 'ihre', 'ihr', 'sich', 'zum', 'zur', 'über', 'durch', 'bieten', 'suchen', 'erfahrung', 'kenntnisse', 'aufgaben', 'stelle', 'mitarbeiter', 'unternehmen'],
        'fr' => ['et', 'le', 'la', 'les', 'des', 'de', 'du', 'pour', 'vous', 'nous', 'avec', 'une', 'un', 'dans', 'est', 'sont', 'sur', 'au', 'aux', 'par', 'ce', 'cette', 'votre', 'notre', 'poste', 'équipe', 'experience', 'expérience', 'entreprise'],
        'es' => ['y', 'el', 'la', 'los', 'las', 'de', 'del', 'para', 'con', 'una', 'un', 'en', 'es', 'por', 'que', 'se', 'su', 'nuestro', 'experiencia', 'equipo', 'empresa', 'puesto', 'trabajo'],
        'pt' => ['e', 'o', 'a', 'os', 'as', 'de', 'do', 'da', 'para', 'com', 'uma', 'um', 'em', 'é', 'por', 'que', 'se', 'sua', 'nosso', 'experiência', 'equipe', 'empresa', 'vaga', 'trabalho', 'você', 'não'],
        'nl' => ['en', 'de', 'het', 'een', 'van', 'voor', 'met', 'je', 'jij', 'wij', 'ons', 'onze', 'bij', 'is', 'zijn', 'op', 'ook', 'als', 'naar', 'ervaring', 'functie', 'bedrijf', 'werken'],
        'it' => ['e', 'il', 'lo', 'la', 'i', 'gli', 'le', 'di', 'del', 'della', 'per', 'con', 'una', 'un', 'in', 'è', 'che', 'si', 'nostro', 'esperienza', 'azienda', 'lavoro', 'cerchiamo'],
    ];

    /** Short function words shared across languages carry little signal; ignore single letters. */
    private const MIN_TOKEN = 2;

    /** @return string ISO 639-1 code; 'en' when English or when unsure */
    public function detect(?string $text): string
    {
        $text = mb_strtolower(trim((string) $text));
        if (mb_strlen($text) < 40) {
            return 'en';
        }

        $text = mb_substr($text, 0, 2500);
        preg_match_all('/[\p{L}]+/u', $text, $m);
        $tokens = array_values(array_filter($m[0], fn ($t) => mb_strlen($t) >= self::MIN_TOKEN));
        $total = count($tokens);
        if ($total < 8) {
            return 'en';
        }

        $freq = array_count_values($tokens);
        $scores = [];
        foreach (self::STOPWORDS as $lang => $words) {
            $hits = 0;
            foreach (array_unique($words) as $w) {
                if (mb_strlen($w) >= self::MIN_TOKEN) {
                    $hits += $freq[$w] ?? 0;
                }
            }
            $scores[$lang] = $hits / $total;
        }

        // German job ads almost always carry the gender marker (m/w/d), a strong tell.
        if (preg_match('/\(\s*(?:m\s*\/\s*w\s*\/\s*d|w\s*\/\s*m\s*\/\s*d|m\s*\/\s*f\s*\/\s*d|all genders)\s*\)/u', $text) && $scores['de'] > 0.03) {
            return 'de';
        }

        arsort($scores);
        $best = array_key_first($scores);
        $bestScore = $scores[$best];
        $en = $scores['en'];

        if ($best === 'en') {
            return 'en';
        }

        // Clearly stronger than English, and a meaningful share of the words.
        return ($bestScore >= 0.13 && $bestScore >= $en * 1.4) ? $best : 'en';
    }
}
