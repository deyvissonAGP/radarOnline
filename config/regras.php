<?php

/*
|--------------------------------------------------------------------------
| Regras do filtro de vagas (públicas)
|--------------------------------------------------------------------------
|
| Estas regras decidem o que conta como vaga de tecnologia. Elas aparecem na
| página /regras do site, por transparência. Todas são expressões regulares
| aplicadas sobre o texto normalizado (minúsculo e sem acento).
|
| Ordem de avaliação:
|   1. "texto_bloqueado": se aparecer em qualquer lugar, não é vaga.
|   2. "titulo_bloqueado": se aparecer no título, não é vaga.
|   3. "areas": o título precisa ser de um cargo de tecnologia de uma das áreas.
|      A primeira área que bater é a da vaga (a ordem importa). Áreas podem ser desligadas com
|      VAGAS_AREAS no .env.
|   4. "cargo_generico": títulos genéricos ("Engineer", "Analista", "Consultor")
|      passam se a descrição citar pelo menos "minimo_tecnologias" tecnologias.
|
| "tecnologias" não decide se é vaga: serve para classificar e filtrar.
|
*/

return [

    'areas' => [
        'seguranca' => [
            'nome' => 'Segurança',
            'titulo' => 'seguranca da informacao|ciberseguranca|seguranca cibernetica|cyber ?security|\b(security|appsec|devsecops) (engineer|analyst|architect|specialist|engenheir[oa]|analista)\b|\bappsec\b|\bpentest(er)?\b|\bsoc (analyst|analista)\b|analista (de )?soc\b|analista de seguranca (da informacao|cibernetica|de ti|ofensiva|defensiva)|\bred team\b|\bblue team\b',
        ],
        'qa' => [
            'nome' => 'QA e testes',
            'titulo' => '\bqa\b|quality assurance|\btestador[a]?\b|qualidade de software|\btester\b|analista de testes?\b|\btest(e|ing)? (automation|automatizado|engineer|analyst)\b|\bsdet\b|automacao de testes',
        ],
        'dados' => [
            'nome' => 'Dados e IA',
            'titulo' => '\b(analista|engenheir[oa]|cientista|arquitet[oa]|especialista|gerente|coordenador[a]?|lider|head) de dados\b|\bdata (engineer|scientist|analyst|architect|analytics|platform|governance|science|engineering)\b|analytics engineer|business intelligence|\bbi\b|power bi|machine learning|\bml\b|\bmlops\b|inteligencia artificial|\b(ai|ia) (engineer|engenheir[oa]|developer|desenvolvedor[a]?|specialist|especialista)\b|\bai/ml\b|\bllm\b|deep learning|computer vision|\bdba\b|database (administrator|engineer|developer)|banco de dados|big data|\betl\b|engenharia de dados|ciencia de dados',
        ],
        'infra' => [
            'nome' => 'Infra, cloud e DevOps',
            'titulo' => '\bdevops\b|\bsre\b|site reliability|\bcloud\b|infraestrutura|infrastructure|platform engineer|engenheir[oa] de plataforma|\bsysadmin\b|administrador[a]? de (sistemas|redes|banco)|analista de (redes|infraestrutura|sistemas operacionais)|network (engineer|administrator|analyst)|\bnoc\b|kubernetes',
        ],
        'desenvolvimento' => [
            'nome' => 'Desenvolvimento',
            'titulo' => '\b(desenvolvedor[a]?|developer|programador[a]?|dev|devs)\b|engenheir[oa] de (software|sistemas|computacao|aplicacoes)|software (engineer|developer|architect|engineering)|engenharia de software|\bfront ?-?end\b|\bback ?-?end\b|\bfull ?-?stack\b|\bmobile\b|\bandroid\b|\bios\b|arquitet[oa] de (software|solucoes|sistemas)|solutions architect|\btech ?lead\b|lider tecnico|desenvolvimento de (software|sistemas)|analista (de )?(sistemas|programador)\b|analista de desenvolvimento de (software|sistemas)|forward deployed engineer|solutions? engineer|\babap\b|\brpa\b|\bsalesforce (developer|engineer)\b|\bgame (developer|programmer)\b|\bweb (developer|development)\b|\bdesenvolvimento web\b|\bwordpress\b|\bmagento\b|\bdrupal\b|\bphp\b|\blaravel\b|\bsymfony\b|\bjava\b|\bpython\b|\bnode(\.?js)?\b|\breact\b|\bangular\b|\.net\b|\bgolang\b|\bflutter\b',
        ],
        'gestao' => [
            'nome' => 'Gestão de tecnologia',
            'titulo' => '\bcto\b|\bcio\b|head (de|of) (tecnologia|engenharia|engineering|technology|produto|product|dados|data|ti)\b|engineering (delivery )?manager|gerente de (ti|tecnologia|engenharia|desenvolvimento|sistemas|projetos de ti)\b|coordenador[a]? de (ti|tecnologia|desenvolvimento|sistemas|engenharia)\b|diretor[a]? de (tecnologia|ti|engenharia)\b|\bvp of engineering\b',
        ],
        'suporte' => [
            'nome' => 'Suporte, TI e ERP',
            'titulo' => '\bsuporte (tecnico|de ti|ti|n[1-3]|de sistemas|a sistemas)\b|analista de (suporte|ti|tecnologia)\b|\bhelp ?desk\b|\bservice desk\b|tecnico (de|em) (ti|informatica|suporte)\b|\bit support\b|\bdesktop support\b|tecnologia da informacao|\bti\b|\bit (analyst|specialist|technician)\b|technical support|support engineer|engenheir[oa] de suporte|consultor[a]? (sap|totvs|protheus|erp|oracle|salesforce)\b',
        ],
        'design' => [
            'nome' => 'Design de produto (UX/UI)',
            'titulo' => '\bux\b|\bui\b|product designer|designer de produto|designer de (interface|experiencia|interacao)|\bux writer\b|ux research(er)?|interaction designer|design system',
        ],
        'produto' => [
            'nome' => 'Produto e agilidade',
            'titulo' => '\bproduct (manager|owner|ops|analyst|lead)\b|gerente de produto|analista de produto|\bpo\b|scrum master|agile (coach|master)|\bagilista\b|analista de requisitos|business analyst|analista de negocios de ti|technical writer|redator[a]? tecnic[oa]',
        ],
    ],

    'cargo_generico' => '\b(engenheir[oa]|engineer|analista|analyst|especialista|specialist|consultor[a]?|consultant|arquitet[oa]|architect|estagi(o|ario|aria)|trainee|intern|lider|lead|coordenador[a]?)\b',

    'minimo_tecnologias' => 3,

    'tecnologias' => [
        'php' => '\bphp\b',
        'laravel' => '\blaravel\b|\blivewire\b|\blumen\b',
        'symfony' => '\bsymfony\b',
        'wordpress' => '\bwordpress\b|\bwoocommerce\b',
        'magento' => '\bmagento\b|\badobe commerce\b',
        'drupal' => '\bdrupal\b',
        'javascript' => '\bjavascript\b|\becmascript\b',
        'typescript' => '\btypescript\b',
        'node.js' => '\bnode(\.?js)?\b|\bnestjs\b',
        'react' => '\breact(\.?js)?\b(?! native)',
        'react native' => '\breact native\b',
        'next.js' => '\bnext\.?js\b',
        'vue' => '\bvue(\.?js)?\b|\bnuxt',
        'angular' => '\bangular\b',
        'python' => '\bpython\b',
        'django' => '\bdjango\b',
        'fastapi' => '\bfastapi\b|\bflask\b',
        'java' => '\bjava\b',
        'spring' => '\bspring( boot)?\b',
        'kotlin' => '\bkotlin\b',
        'c#/.net' => '\bc#|\.net\b|\bdotnet\b',
        'go' => '\bgolang\b|\bgo (lang|developer|engineer|backend)\b',
        'ruby' => '\bruby\b|\brails\b',
        'elixir' => '\belixir\b',
        'rust' => '\brust\b',
        'c++' => '\bc\+\+',
        'swift' => '\bswift\b|\bswiftui\b',
        'flutter' => '\bflutter\b|\bdart\b',
        'sql' => '\bsql\b|\bmysql\b|\bpostgres(ql)?\b|\bsql server\b|\boracle\b',
        'mongodb' => '\bmongo(db)?\b',
        'aws' => '\baws\b|amazon web services',
        'azure' => '\bazure\b',
        'gcp' => '\bgcp\b|google cloud',
        'docker' => '\bdocker\b',
        'kubernetes' => '\bkubernetes\b|\bk8s\b',
        'terraform' => '\bterraform\b',
        'spark' => '\bspark\b|\bdatabricks\b',
        'power bi' => '\bpower ?bi\b',
        'sap' => '\bsap\b|\babap\b',
        'salesforce' => '\bsalesforce\b|\bapex\b',
        'figma' => '\bfigma\b',
    ],

    'titulo_bloqueado' => [
        'curso' => '\bcurso(s)?\b|\bbootcamp\b|\bformacao\b|\btreinamento\b|\baula(s)?\b|\bmentoria\b|\bworkshop\b|\bwebinar\b|\be-?book\b',
        'lista de vagas' => '\b(grupo|lista|canal|comunidade|newsletter) de vagas\b|\bvagas da semana\b|\bvagas do dia\b',
        'servico de curriculo' => '\bcurriculo\b|\bcv\b|\bresume\b|\blinkedin (otimizado|profissional)\b',
        'procurando vaga' => '\b(procuro|busco|em busca de|disponivel para|open to work|#opentowork)\b',
        'evento' => '\bmeetup\b|\bpalestra\b|\bconferencia\b|\bhackathon\b',
    ],

    'texto_bloqueado' => [
        'link de grupo' => 'chat\.whatsapp\.com|t\.me/|entre no (nosso )?grupo|link do grupo',
        'venda de curso' => 'inscreva-se no curso|garanta sua vaga na turma|matriculas abertas|desconto na inscricao',
        'servico de curriculo' => 'revisao de curriculo|turbine seu curriculo|montamos seu curriculo',
    ],

];
