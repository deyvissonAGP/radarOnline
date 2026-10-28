<?php

namespace App\Radar;

final class Text
{
    /** Minúsculas, sem acentos e com espaços simples. */
    public static function normalize(?string $text): string
    {
        $text = mb_strtolower((string) $text, 'UTF-8');
        $text = strtr($text, [
            'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
            'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
            'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
            'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
            'ç' => 'c', 'ñ' => 'n',
        ]);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }

    /**
     * Impressão digital usada para achar a mesma vaga publicada em fontes diferentes.
     * Junta título e empresa, sem acentos, pontuação, marcadores [..] ou palavras de ligação.
     */
    public static function fingerprint(string $title, ?string $company): string
    {
        $clean = function (?string $value): string {
            $value = self::normalize($value);
            $value = (string) preg_replace('/\[[^\]]*\]|\([^)]*\)/u', ' ', $value);
            $value = (string) preg_replace('/[^a-z0-9 ]+/u', ' ', $value);
            $value = (string) preg_replace('/\b(de|da|do|das|dos|em|para|e|a|o|the|of|vaga|remoto|remota|hibrido|presencial|clt|pj)\b/u', ' ', $value);

            return trim((string) preg_replace('/\s+/', ' ', $value));
        };

        return sha1($clean($title).'|'.$clean($company));
    }

    /** Remove HTML e espaços excessivos. */
    public static function stripHtml(?string $html): string
    {
        $text = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</li>'], "\n", (string) $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace("/[ \t]+/u", ' ', (string) preg_replace("/\n{3,}/", "\n\n", $text)));
    }
}
