<?php

return [

    // Usuário e senha do site (HTTP Basic). Em produção, sem senha o site fica bloqueado.
    'acesso' => [
        'usuario' => env('RADAR_USUARIO', 'radar'),
        'senha' => env('RADAR_SENHA', ''),
    ],

    // /saude responde 503 se a última coleta tiver mais horas que isso:
    // sinal de que o agendador parou. Aponte um monitor externo (UptimeRobot) para lá.
    'saude_max_horas' => (int) env('RADAR_SAUDE_HORAS', 4),

];
