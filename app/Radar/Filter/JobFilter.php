<?php

namespace App\Radar\Filter;

use App\Radar\JobData;
use App\Radar\Text;

/**
 * Decide se um item coletado é de fato uma vaga de tecnologia e de qual área.
 * As regras vêm de config/regras.php e são públicas.
 */
final class JobFilter
{
    /**
     * @param  array<string, mixed>  $regras
     * @param  string[]  $areasAtivas  Áreas aceitas; vazio = todas
     */
    public function __construct(
        private readonly array $regras,
        private readonly array $areasAtivas = [],
    ) {}

    public function avaliar(JobData $job): Avaliacao
    {
        // "Engenheiro(a) de Dados" → "Engenheiro de Dados", para os padrões baterem.
        $titulo = (string) preg_replace('~\((a|o|as|os|e|es)\)~u', '', Text::normalize($job->title));
        $texto = Text::normalize($job->searchableText());

        foreach ($this->regras['texto_bloqueado'] ?? [] as $nome => $padrao) {
            if ($this->bate($padrao, $texto)) {
                return Avaliacao::rejeitada("texto: {$nome}");
            }
        }

        foreach ($this->regras['titulo_bloqueado'] ?? [] as $nome => $padrao) {
            if ($this->bate($padrao, $titulo)) {
                return Avaliacao::rejeitada("título: {$nome}");
            }
        }

        $area = $this->area($titulo);

        // Título genérico ("Engineer", "Analista") com a descrição cheia de tecnologia também conta.
        if ($area === null && isset($this->regras['cargo_generico']) && $this->bate($this->regras['cargo_generico'], $titulo)
            && $this->contarTecnologias($texto) >= (int) ($this->regras['minimo_tecnologias'] ?? 3)) {
            $area = 'outras';
        }

        if ($area === null) {
            return Avaliacao::rejeitada('título não parece vaga de tecnologia');
        }

        if ($this->areasAtivas && ! in_array($area, $this->areasAtivas, true)) {
            return Avaliacao::rejeitada("área desligada: {$area}", $area);
        }

        return Avaliacao::aceita($area);
    }

    /** Primeira área cujo padrão bate no título. */
    public function area(string $tituloNormalizado): ?string
    {
        foreach ($this->regras['areas'] ?? [] as $chave => $area) {
            if ($this->bate($area['titulo'], $tituloNormalizado)) {
                return $chave;
            }
        }

        return null;
    }

    private function contarTecnologias(string $texto): int
    {
        return count(array_filter(
            $this->regras['tecnologias'] ?? [],
            fn (string $padrao) => $this->bate($padrao, $texto),
        ));
    }

    private function bate(string $padrao, string $texto): bool
    {
        return (bool) preg_match('~'.str_replace('~', '\~', $padrao).'~u', $texto);
    }
}
