<?php

return [

    // Só contam vagas publicadas dentro desta janela.
    'janela_horas' => (int) env('VAGAS_JANELA_HORAS', 72),

    // Fuso usado para exibir datas e no resumo diário.
    'fuso' => env('VAGAS_FUSO', 'America/Sao_Paulo'),

    // Vagas mais antigas que isso são apagadas do banco.
    'manter_dias' => (int) env('VAGAS_MANTER_DIAS', 30),

    // Áreas aceitas pelo filtro (chaves de config/regras.php, separadas por vírgula).
    // Vazio = todas: desenvolvimento, dados, infra, qa, seguranca, design, produto, gestao, suporte, outras.
    'areas' => array_values(array_filter(array_map('trim', explode(',', (string) env('VAGAS_AREAS', ''))))),

    // Termos usados nas buscas das fontes que aceitam pesquisa (Gupy, LinkedIn).
    // Amplos de propósito: o filtro decide depois o que é vaga de tecnologia.
    'termos' => [
        'desenvolvedor', 'developer', 'programador', 'engenheiro de software', 'software',
        'dados', 'data', 'devops', 'cloud', 'infraestrutura', 'qa', 'analista de sistemas',
        'tecnologia da informação', 'suporte técnico', 'ux', 'segurança da informação', 'product',
    ],

    'fontes' => [

        'gupy' => [
            'ativa' => env('FONTE_GUPY', true),
            'classe' => App\Radar\Collectors\GupyCollector::class,
            // Páginas de 12 vagas por termo (a Gupy entrega no máximo 100 por busca);
            // para antes se passar da janela de horas.
            'paginas' => 9,
        ],

        'github' => [
            'ativa' => env('FONTE_GITHUB', true),
            'classe' => App\Radar\Collectors\GithubIssuesCollector::class,
            // Sem token: 60 requisições/hora. Com token (sem permissão nenhuma): 5.000/hora.
            'token' => env('GITHUB_TOKEN'),
            'repositorios' => array_filter(explode(',', (string) env('GITHUB_REPOSITORIOS', 'backend-br/vagas,frontendbr/vagas,react-brasil/vagas,soujava/vagas-java,qa-brasil/vagas,androiddevbr/vagas,CocoaHeadsBrasil/vagas,datascience-br/vagas,uxbrasil/vagas,vuejs-br/vagas,phpdevbr/vagas'))),
        ],

        // Vagas remotas abertas para quem mora no Brasil. API gratuita; os termos
        // pedem link para a vaga original e crédito à Himalayas (o Radar faz os dois).
        'himalayas' => [
            'ativa' => env('FONTE_HIMALAYAS', true),
            'classe' => App\Radar\Collectors\HimalayasCollector::class,
            'pais' => env('HIMALAYAS_PAIS', 'Brazil'),
            // Lê todas as vagas da mais nova para a mais antiga (20 por página) até sair da janela.
            'paginas' => 25,
        ],

        // Experimental: formato não documentado, confira no primeiro teste.
        'remotar' => [
            'ativa' => env('FONTE_REMOTAR', true),
            'classe' => App\Radar\Collectors\RemotarCollector::class,
            'endpoint' => env('REMOTAR_ENDPOINT', 'https://api.remotar.com.br/jobs'),
            // Lê da mais nova para a mais antiga (50 por página) até sair da janela.
            'paginas' => 12,
        ],

        // Experimental: lê o HTML da listagem geral (15 vagas por página).
        'programathor' => [
            'ativa' => env('FONTE_PROGRAMATHOR', true),
            'classe' => App\Radar\Collectors\ProgramathorCollector::class,
            'paginas' => 4,
        ],

        // Desligado por padrão: os termos do LinkedIn proíbem coleta automatizada.
        'linkedin' => [
            'ativa' => env('FONTE_LINKEDIN', false),
            'classe' => App\Radar\Collectors\LinkedInCollector::class,
            'localizacao' => env('LINKEDIN_LOCALIZACAO', 'Brasil'),
        ],
    ],

    'http' => [
        'timeout' => 20,
        'user_agent' => env('VAGAS_USER_AGENT', 'Mozilla/5.0 (compatible; RadarVagas/1.0)'),
    ],

    'telegram' => [
        'token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
        // Máximo de vagas enviadas por rodada, para não virar spam.
        'max_por_rodada' => (int) env('TELEGRAM_MAX_POR_RODADA', 15),
        // Filtros pessoais (separados por vírgula). Vazio = todas. Vagas sem a
        // informação (modelo ou nível não identificado) são enviadas mesmo assim.
        'modelos' => array_filter(explode(',', (string) env('TELEGRAM_MODELOS', ''))),
        'niveis' => array_filter(explode(',', (string) env('TELEGRAM_NIVEIS', ''))),
        // Estes dois são estritos: a vaga precisa ser de uma das áreas / citar uma das tecnologias.
        'areas' => array_filter(explode(',', (string) env('TELEGRAM_AREAS', ''))),
        'tecnologias' => array_filter(explode(',', (string) env('TELEGRAM_TECNOLOGIAS', ''))),
    ],

    // Avisa no Telegram quando uma fonte falha ou volta vazia este número de coletas seguidas.
    'alerta_falhas_seguidas' => (int) env('VAGAS_ALERTA_FALHAS', 2),

];
