<?php
// Desarranchar (Militares) — versão administrativa: o furriel/admin remove o arranchamento
// de vários militares, dias e refeições de uma vez. Espelha rancho/controllers/arranchar.php,
// mas apaga em vez de inserir. Diferente do autoarranchamento do militar (controllers/registros.php),
// aqui não se aplica o prazo de 2 dias / sexta-feira: é uma correção administrativa.

session_start();

header('Content-Type: application/json; charset=utf-8');

include "../../controllers/config/conexao.php";
include "auditoria.php";

function responder($tipo, $mensagem, $codigo = 200)
{
    http_response_code($codigo);

    echo json_encode(
        [$tipo => $mensagem],
        JSON_UNESCAPED_UNICODE
    );

    exit;
}

// Só usuários logados do painel administrativo (furriel ou admin) podem desarranchar.
if (empty($_SESSION['usuario']['id'])) {
    responder('error', 'Sessão expirada, faça login novamente.', 401);
}

try {
    $cnt->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $militares = $_POST['militares'] ?? [];
    $refeicoes = $_POST['refeicao'] ?? [];
    $diasTexto = trim($_POST['dias'] ?? '');

    if (!is_array($militares)) {
        $militares = [$militares];
    }

    if (!is_array($refeicoes)) {
        $refeicoes = [$refeicoes];
    }

    $militares = array_values(
        array_filter(
            array_unique(
                array_map('trim', $militares)
            )
        )
    );

    $refeicoes = array_values(
        array_intersect(
            [1, 2, 3],
            array_map('intval', $refeicoes)
        )
    );

    // O Operador (furriel) só arrancha/desarrancha militares da própria Cia.
    if ((int)($_SESSION['usuario']['tipo'] ?? 0) !== 1 && count($militares) > 0) {
        $marcas = implode(',', array_fill(0, count($militares), '?'));
        $daCia = $cnt->prepare("SELECT ident_militar FROM cad_militar WHERE om_id = ? AND ident_militar IN ($marcas)");
        $daCia->execute(array_merge([(int)$_SESSION['usuario']['om']], $militares));
        $permitidos = $daCia->fetchAll(PDO::FETCH_COLUMN);
        if (count($permitidos) !== count($militares)) {
            responder('error', 'Você só pode arranchar ou desarranchar militares da sua Cia!');
        }
    }

    if (count($militares) === 0) {
        responder('error', 'Selecione pelo menos um militar!');
    }

    if ($diasTexto === '') {
        responder('error', 'Selecione pelo menos uma data!');
    }

    if (count($refeicoes) === 0) {
        responder('error', 'Selecione pelo menos uma refeição!');
    }

    $diasRecebidos = preg_split(
        '/\s*[,;]\s*/',
        $diasTexto,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    $datas = [];

    foreach ($diasRecebidos as $dia) {
        $dia = trim($dia);

        $data = DateTime::createFromFormat('!d/m/Y', $dia);
        $erros = DateTime::getLastErrors();

        if (
            !$data ||
            (
                $erros !== false &&
                (
                    $erros['warning_count'] > 0 ||
                    $erros['error_count'] > 0
                )
            )
        ) {
            responder('error', 'Data inválida: ' . $dia);
        }

        $datas[] = $data->format('Y-m-d');
    }

    $datas = array_values(array_unique($datas));

    $buscar = $cnt->prepare(
        "SELECT id
         FROM registros
         WHERE ident_militar = :militar
           AND tp = :refeicao
           AND data = :data
         LIMIT 1"
    );

    $apagar = $cnt->prepare(
        "DELETE FROM registros WHERE id = :id"
    );

    $removidos = 0;
    $inexistentes = 0;
    $usuarioId = $_SESSION['usuario']['id'];

    foreach ($datas as $dataBanco) {
        foreach ($refeicoes as $refeicao) {
            foreach ($militares as $militar) {

                $buscar->execute([
                    ':militar' => $militar,
                    ':refeicao' => $refeicao,
                    ':data' => $dataBanco
                ]);

                $registro = $buscar->fetchColumn();

                if ($registro === false) {
                    $inexistentes++;
                    continue;
                }

                $apagar->execute([':id' => $registro]);
                $removidos++;

                try {
                    auditoria("DESARANCHAR", (int)$registro, $cnt, $usuarioId);
                } catch (Throwable $erroAuditoria) {
                    error_log('Erro na auditoria: ' . $erroAuditoria->getMessage());
                }
            }
        }
    }

    if ($removidos === 0) {
        responder(
            'success',
            'Nenhum registro removido. O(s) militar(es) não estava(m) arranchado(s) nas datas e refeições selecionadas.'
        );
    }

    responder(
        'success',
        'Desarranchamento realizado! ' .
        $removidos .
        ' registro(s) removido(s). ' .
        $inexistentes .
        ' não encontrado(s).'
    );

} catch (Throwable $erro) {
    error_log('Erro ao desarranchar: ' . $erro->getMessage());

    responder(
        'error',
        'Erro ao remover no banco: ' . $erro->getMessage(),
        500
    );
}
