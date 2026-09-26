<?php

namespace App\Services\Insights;

/**
 * Remove dados pessoais dos textos antes de enviá-los à camada gratuita do
 * Gemini (docs/15, seção 3.3). É uma heurística: reduz a exposição, mas não
 * garante anonimização completa (ex.: um nome escrito em minúsculas).
 */
class InsightAnonymizer
{
    /**
     * Palavras capitalizadas no meio da frase que não são nomes de pessoas
     * nem de lugares e podem ficar como estão.
     */
    protected const ALLOWED_CAPITALIZED = [
        'Segunda', 'Terça', 'Quarta', 'Quinta', 'Sexta', 'Sábado', 'Domingo',
        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho',
        'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
        'Deus', 'Natal', 'Páscoa', 'Ano', 'Novo', 'Carnaval',
    ];

    /** Títulos que não identificam ninguém sozinhos. */
    protected const TITLES = ['dr', 'dra', 'sr', 'sra', 'prof', 'profa'];

    /** @var array<int, string> */
    protected array $names;

    /**
     * @param  array<int, string|null>  $fullNames  Nomes conhecidos (paciente, psicóloga) a remover.
     */
    public function __construct(array $fullNames = [])
    {
        $this->names = collect($fullNames)
            ->filter()
            ->flatMap(fn ($name) => preg_split('/[\s.]+/u', $name))
            ->filter(fn ($part) => mb_strlen($part) >= 3 && ! in_array(mb_strtolower($part), self::TITLES, true))
            ->unique()
            ->sortByDesc(fn ($part) => mb_strlen($part))
            ->values()
            ->all();
    }

    public function anonymize(?string $text): ?string
    {
        if ($text === null || trim($text) === '') {
            return $text;
        }

        $text = preg_replace('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', '[email]', $text);
        $text = preg_replace('#\b(?:https?://|www\.)\S+#iu', '[link]', $text);
        $text = preg_replace('/\b\d{3}\.?\d{3}\.?\d{3}-?\d{2}\b/u', '[documento]', $text);
        $text = preg_replace('/(?:\+?55\s?)?(?:\(?\d{2}\)?\s?)?9?\d{4}[-\s]?\d{4}\b/u', '[telefone]', $text);
        $text = preg_replace('/\b\d{5,}\b/u', '[número]', $text);

        foreach ($this->names as $name) {
            $text = preg_replace('/(?<!\p{L})'.preg_quote($name, '/').'(?!\p{L})/iu', '[nome]', $text);
        }

        // Palavra capitalizada que não inicia frase: provável nome próprio.
        return preg_replace_callback(
            '/(?<=[\p{Ll}\p{N},;:)]\s)\p{Lu}\p{Ll}+/u',
            fn ($m) => in_array($m[0], self::ALLOWED_CAPITALIZED, true) ? $m[0] : '[nome]',
            $text
        );
    }
}
