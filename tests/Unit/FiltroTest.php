<?php

namespace Tests\Unit;

use App\Radar\Classifier;
use App\Radar\Filter\JobFilter;
use App\Radar\JobData;
use App\Radar\Text;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class FiltroTest extends TestCase
{
    private function filtro(array $areasAtivas = []): JobFilter
    {
        return new JobFilter(require __DIR__.'/../../config/regras.php', $areasAtivas);
    }

    private function vaga(string $titulo, ?string $descricao = null, array $tags = []): JobData
    {
        return new JobData('teste', '1', $titulo, 'https://exemplo.com', 'Empresa', null, null, null, $descricao, $tags);
    }

    public static function aceitas(): array
    {
        return [
            ['Desenvolvedor PHP Pleno', null],
            ['Pessoa Desenvolvedora Back-end', 'Experiência com Laravel e filas'],
            ['Analista de Sistemas Sênior - WordPress', null],
            ['Software Engineer (Symfony)', null],
            ['Desenvolvedor PHP (3 vagas)', null],
            ['Tech Lead Magento', null],
            ['DevOps Sênior', 'Infraestrutura para aplicações PHP'],
            ['Web Development Lead', 'Our stack: WordPress and PHP'],
            ['Desenvolvedor Node.js', 'TypeScript e AWS'],
            ['Engenheiro(a) de Dados Sênior', null],
            ['QA Engineer', null],
            ['Product Designer Sr', null],
            ['Analista de Suporte Técnico N2', null],
        ];
    }

    #[DataProvider('aceitas')]
    public function test_aceita_vagas_reais(string $titulo, ?string $descricao): void
    {
        $this->assertTrue($this->filtro()->avaliar($this->vaga($titulo, $descricao))->aceita);
    }

    public static function rejeitadas(): array
    {
        return [
            ['Curso de PHP do zero', null, 'título: curso'],
            ['Grupo de vagas PHP', null, 'título: lista de vagas'],
            ['Desenvolvedor PHP', 'Entre no grupo: https://chat.whatsapp.com/abc', 'texto: link de grupo'],
            ['Turbine seu currículo para vagas PHP', null, 'texto: servico de curriculo'],
            ['Vendedor', 'Vendas de sistemas em PHP', 'título não parece vaga de tecnologia'],
            ['Analista de Desenvolvimento Organizacional', 'RH e treinamento', 'título não parece vaga de tecnologia'],
            ['Designer Gráfico', 'Photoshop e Illustrator', 'título não parece vaga de tecnologia'],
            ['Engenheiro Civil', 'Obras e AutoCAD', 'título não parece vaga de tecnologia'],
            ['Analista de Segurança Patrimonial', null, 'título não parece vaga de tecnologia'],
            ['Data Entry Clerk', null, 'título não parece vaga de tecnologia'],
            ['Procuro vaga de desenvolvedor PHP', null, 'título: procurando vaga'],
        ];
    }

    #[DataProvider('rejeitadas')]
    public function test_rejeita_o_que_nao_e_vaga(string $titulo, ?string $descricao, string $motivo): void
    {
        $avaliacao = $this->filtro()->avaliar($this->vaga($titulo, $descricao));

        $this->assertFalse($avaliacao->aceita);
        $this->assertSame($motivo, $avaliacao->motivo);
    }

    public static function areas(): array
    {
        return [
            ['Desenvolvedor(a) PHP Pleno', 'desenvolvimento'],
            ['Senior Software Engineer', 'desenvolvimento'],
            ['Cientista de Dados', 'dados'],
            ['Analista de Dados Pleno', 'dados'],
            ['Senior DevOps Engineer', 'infra'],
            ['Analista de Testes', 'qa'],
            ['Analista de Segurança da Informação', 'seguranca'],
            ['UX/UI Designer', 'design'],
            ['Product Owner', 'produto'],
            ['Gerente de TI', 'gestao'],
            ['Técnico de Suporte N1', 'suporte'],
        ];
    }

    #[DataProvider('areas')]
    public function test_identifica_a_area(string $titulo, string $area): void
    {
        $avaliacao = $this->filtro()->avaliar($this->vaga($titulo));

        $this->assertTrue($avaliacao->aceita);
        $this->assertSame($area, $avaliacao->area);
    }

    public function test_cargo_generico_precisa_de_tecnologias_na_descricao(): void
    {
        $aceita = $this->filtro()->avaliar($this->vaga('Principal Engineer', 'Stack: Python, AWS, Kubernetes e PostgreSQL.'));
        $this->assertTrue($aceita->aceita);
        $this->assertSame('outras', $aceita->area);

        $rejeitada = $this->filtro()->avaliar($this->vaga('Principal Engineer', 'Projetos de pontes e viadutos.'));
        $this->assertFalse($rejeitada->aceita);
    }

    public function test_areas_desligadas(): void
    {
        $avaliacao = $this->filtro(['desenvolvimento', 'dados'])->avaliar($this->vaga('Analista de Suporte Técnico'));

        $this->assertFalse($avaliacao->aceita);
        $this->assertSame('área desligada: suporte', $avaliacao->motivo);
        $this->assertTrue($this->filtro(['desenvolvimento', 'dados'])->avaliar($this->vaga('Desenvolvedor PHP'))->aceita);
    }

    public function test_classifica_modelo_senioridade_contrato_e_tecnologias(): void
    {
        $regras = require __DIR__.'/../../config/regras.php';
        $resultado = (new Classifier($regras['tecnologias']))->classificar(
            $this->vaga('Desenvolvedor PHP Sênior (Remoto)', 'Laravel, Livewire e Docker. Contrato PJ.'),
        );

        $this->assertSame(['php', 'laravel', 'docker'], $resultado['tecnologias']);
        $this->assertSame('remoto', $resultado['modelo']);
        $this->assertSame('senior', $resultado['senioridade']);
        $this->assertSame('pj', $resultado['contrato']);
    }

    public function test_impressao_digital_ignora_marcadores_e_acentos(): void
    {
        $this->assertSame(
            Text::fingerprint('Desenvolvedor PHP Sênior [Remoto]', 'Acme Tecnologia'),
            Text::fingerprint('desenvolvedor php senior', 'ACME Tecnologia'),
        );
        $this->assertNotSame(
            Text::fingerprint('Desenvolvedor PHP', 'Acme'),
            Text::fingerprint('Desenvolvedor PHP', 'Beta'),
        );
    }
}
