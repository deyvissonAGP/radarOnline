<?php

namespace Tests\Unit;

use App\Radar\Salario;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SalarioTest extends TestCase
{
    public static function casos(): array
    {
        return [
            ['Salário: R$ 6.500,00 + benefícios', [6500, 6500]],
            ['Faixa salarial de R$ 5.000 a R$ 7.000', [5000, 7000]],
            ['Remuneração entre R$ 5 mil e 7 mil', [5000, 7000]],
            ['R$ 8k - 12k, PJ', [8000, 12000]],
            ['Até R$ 4.000', [4000, 4000]],
            ['Remuneração R$ 3.500 e VA R$ 800', [3500, 3500]],
            ['VR de R$ 1.200 por mês', null],
            ['Auxílio home office R$ 150', null],
            ['Pagamos R$ 30 por dia', null],
            ['Investimos R$ 2.000.000 em tecnologia', null],
            ['Vaga sem valores', null],
        ];
    }

    #[DataProvider('casos')]
    public function test_extrai_faixa_mensal(string $texto, ?array $esperado): void
    {
        $resultado = Salario::extrair($texto);

        $this->assertSame($esperado, $resultado ? [$resultado['min'], $resultado['max']] : null);
    }
}
