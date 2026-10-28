<?php

namespace App\Radar;

/**
 * Encontra a faixa salarial mensal em reais no texto da vaga.
 *
 * Para não confundir salário com benefício ("VR de R$ 800", "auxílio home office R$ 150"),
 * só aceita valores entre R$ 1.000 e R$ 60.000 que estejam numa faixa ("R$ 5.000 a R$ 7.000")
 * ou perto de uma palavra como salário, remuneração, faixa ou bolsa.
 */
final class Salario
{
    private const VALOR = 'r\$\s*(\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{2})?\s*(k|mil)?';

    private const CONTEXTO = '\b(salario|salarial|remuneracao|faixa|bolsa|compensation|salary|ate)\b';

    private const BENEFICIO = '\b(vale|va|vr|auxilio|beneficio|plr|bonus|ajuda de custo|home office|alimentacao|refeicao|transporte|gympass|wellhub|reembolso)\b';

    /** @return array{min: int, max: int, moeda: string}|null */
    public static function extrair(?string $texto): ?array
    {
        $texto = Text::normalize($texto);
        if (! str_contains($texto, 'r$')) {
            return null;
        }

        // Faixa: "R$ 5.000 a R$ 7.000", "entre R$ 5 mil e 7 mil", "R$ 8k - 12k".
        $faixa = '~'.self::VALOR.'\s*(?:a|ate|-|–|e)\s*(?:r\$\s*)?(\d{1,3}(?:\.\d{3})+|\d+)(?:,\d{2})?\s*(k|mil)?~u';
        if (preg_match_all($faixa, $texto, $faixas, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($faixas as $m) {
                $sufixo = ($m[2][0] ?? '') !== '' ? $m[2][0] : ($m[4][0] ?? '');
                $min = self::valor($m[1][0], $sufixo);
                $max = self::valor($m[3][0], ($m[4][0] ?? '') !== '' ? $m[4][0] : $sufixo);
                if (self::plausivel($min) && self::plausivel($max) && $max >= $min && ! self::ehBeneficio($texto, $m[0][1])) {
                    return ['min' => $min, 'max' => $max, 'moeda' => 'BRL'];
                }
            }
        }

        // Valor único perto de uma palavra de salário: "Salário: R$ 6.500", "até R$ 4.000".
        if (preg_match_all('~'.self::VALOR.'~u', $texto, $valores, PREG_SET_ORDER | PREG_OFFSET_CAPTURE)) {
            foreach ($valores as $m) {
                $valor = self::valor($m[1][0], $m[2][0] ?? '');
                $antes = mb_substr(substr($texto, 0, $m[0][1]), -40);
                if (self::plausivel($valor) && preg_match('~'.self::CONTEXTO.'~u', $antes) && ! self::ehBeneficio($texto, $m[0][1])) {
                    return ['min' => $valor, 'max' => $valor, 'moeda' => 'BRL'];
                }
            }
        }

        return null;
    }

    private static function valor(string $numero, string $sufixo): int
    {
        $valor = (int) str_replace('.', '', $numero);

        return $sufixo !== '' ? $valor * 1000 : $valor;
    }

    private static function plausivel(int $valor): bool
    {
        return $valor >= 1000 && $valor <= 60000;
    }

    /** Olha as palavras logo antes do valor: "VR de R$ 1.200" é benefício, não salário. */
    private static function ehBeneficio(string $texto, int $posicao): bool
    {
        return (bool) preg_match('~'.self::BENEFICIO.'~u', mb_substr(substr($texto, 0, $posicao), -30));
    }
}
