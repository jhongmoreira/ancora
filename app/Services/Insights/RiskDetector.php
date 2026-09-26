<?php

namespace App\Services\Insights;

use Illuminate\Support\Str;

/**
 * Procura expressões de risco (ideação suicida, autolesão) nos textos dos
 * registros. Deliberadamente NÃO usa a IA: LLMs erram justamente nisso,
 * sobretudo na ideação passiva (docs/15, seção 5.4). Prefere falso positivo
 * a falso negativo — o alerta só pede atenção, não diagnostica.
 */
class RiskDetector
{
    /** Padrões sobre o texto em minúsculas e sem acentos. */
    protected const PATTERNS = [
        '/\bsuicid/',
        '/\bme mata(r|ria)?\b/',
        '/\bme mato\b/',
        '/\bmatar a mim\b/',
        '/\b(tirar|acabar com) (a )?minha (propria )?vida\b/',
        '/\bnao (quero|queria|aguento) mais viver\b/',
        '/\b(quero|queria|vontade de|desejo de|pensei em) morrer\b/',
        '/\bmelhor (se eu )?(estivesse |estar )?morto\b/',
        '/\bsumir (de vez|para sempre|pra sempre|do mundo)\b/',
        '/\bnao ver sentido (em|na) (viver|vida)\b/',
        '/\bme (machucar|cortar|ferir|machuquei|cortei|feri)\b/',
        '/\bautoles/',
        '/\bautomutila/',
    ];

    /** Expressões idiomáticas que contêm as palavras acima sem indicar risco. */
    protected const IDIOMS = [
        '/morr(er|endo|i) de (rir|vergonha|saudade|fome|sono|medo|calor|frio|tedio|cansaco)/',
        '/(quero|queria) morrer de (rir|vergonha)/',
    ];

    /**
     * @param  array<string, array<int, string|null>>  $textsByRecord  Textos por id de registro (ex.: "E12").
     * @return array<int, string> Ids dos registros com conteúdo de risco.
     */
    public function detect(array $textsByRecord): array
    {
        $flagged = [];

        foreach ($textsByRecord as $recordId => $texts) {
            foreach ($texts as $text) {
                if ($text !== null && $this->isRisky($text)) {
                    $flagged[] = $recordId;

                    break;
                }
            }
        }

        return $flagged;
    }

    public function isRisky(string $text): bool
    {
        $normalized = Str::lower(Str::ascii($text));

        foreach (self::IDIOMS as $idiom) {
            $normalized = preg_replace($idiom, ' ', $normalized);
        }

        foreach (self::PATTERNS as $pattern) {
            if (preg_match($pattern, $normalized)) {
                return true;
            }
        }

        return false;
    }
}
