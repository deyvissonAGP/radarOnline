<?php

namespace App\Radar;

/**
 * Extrai características da vaga a partir do texto:
 * tecnologias, modelo de trabalho, senioridade e tipo de contrato.
 */
final class Classifier
{
    /** @param  array<string, string>  $tecnologias  nome => regex (de config/regras.php) */
    public function __construct(private readonly array $tecnologias) {}

    /** @return array{tecnologias: string[], modelo: ?string, senioridade: ?string, contrato: ?string} */
    public function classificar(JobData $job): array
    {
        $texto = Text::normalize($job->searchableText());
        $titulo = Text::normalize($job->title);

        return [
            'tecnologias' => $this->tecnologias($texto),
            'modelo' => $this->modelo($job, $texto),
            'senioridade' => $this->senioridade($titulo) ?? $this->senioridade($texto),
            'contrato' => $this->contrato($texto),
        ];
    }

    /** @return string[] */
    private function tecnologias(string $texto): array
    {
        $achadas = [];
        foreach ($this->tecnologias as $nome => $padrao) {
            if (preg_match('~'.$padrao.'~u', $texto)) {
                $achadas[] = $nome;
            }
        }

        return $achadas;
    }

    private function modelo(JobData $job, string $texto): ?string
    {
        $tipo = Text::normalize($job->workplaceType);
        if (in_array($tipo, ['remote', 'remoto', 'remota'], true)) {
            return 'remoto';
        }
        if (in_array($tipo, ['hybrid', 'hibrido'], true)) {
            return 'hibrido';
        }
        if (in_array($tipo, ['on-site', 'onsite', 'presencial'], true)) {
            return 'presencial';
        }

        if (preg_match('~\bh[i]brid[oa]\b|\bhybrid\b~u', $texto)) {
            return 'hibrido';
        }
        if (preg_match('~\bremot[oa]\b|\bremote\b|home ?office|\bwork from home\b|\banywhere\b|\b100% remot~u', $texto)) {
            return 'remoto';
        }
        if (preg_match('~\bpresencial\b|\bon-?site\b~u', $texto)) {
            return 'presencial';
        }

        return null;
    }

    private function senioridade(string $texto): ?string
    {
        $mapa = [
            'estagio' => '\bestagi(o|ario|aria)\b|\bintern(ship)?\b',
            'junior' => '\bjunior\b|\bjr\.?\b',
            'pleno' => '\bpleno\b|\bmid(-level)?\b|\bpl\b',
            'senior' => '\bsenior\b|\bsr\.?\b',
            'especialista' => '\bespecialista\b|\bstaff\b|\bprincipal\b|\btech ?lead\b|\blider tecnico\b',
        ];
        foreach ($mapa as $nivel => $padrao) {
            if (preg_match('~'.$padrao.'~u', $texto)) {
                return $nivel;
            }
        }

        return null;
    }

    private function contrato(string $texto): ?string
    {
        $clt = (bool) preg_match('~\bclt\b|\bcarteira assinada\b~u', $texto);
        $pj = (bool) preg_match('~\bpj\b|\bpessoa juridica\b~u', $texto);

        return match (true) {
            $clt && $pj => 'clt/pj',
            $clt => 'clt',
            $pj => 'pj',
            default => null,
        };
    }
}
