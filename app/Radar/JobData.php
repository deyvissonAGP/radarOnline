<?php

namespace App\Radar;

use DateTimeImmutable;

/**
 * Vaga normalizada, independente da fonte de onde veio.
 * Todo coletor converte o formato da sua fonte para este objeto.
 */
final class JobData
{
    /**
     * @param  string[]  $tags  Etiquetas vindas da própria fonte (ex.: labels do GitHub)
     * @param  array{min: int, max: int, moeda: string}|null  $salary  Faixa mensal, quando a fonte informa em campo próprio
     */
    public function __construct(
        public readonly string $source,
        public readonly string $externalId,
        public readonly string $title,
        public readonly string $url,
        public readonly ?string $company = null,
        public readonly ?string $location = null,
        public readonly ?string $workplaceType = null,
        public readonly ?DateTimeImmutable $publishedAt = null,
        public readonly ?string $description = null,
        public readonly array $tags = [],
        public readonly ?array $salary = null,
    ) {}

    /** Texto usado pelo filtro e pelo classificador. */
    public function searchableText(): string
    {
        return trim(implode("\n", array_filter([
            $this->title,
            $this->company,
            $this->location,
            $this->workplaceType,
            implode(' ', $this->tags),
            mb_substr((string) $this->description, 0, 5000),
        ])));
    }
}
