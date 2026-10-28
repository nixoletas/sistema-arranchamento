<?php

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

// Só usuários logados do painel administrativo (furriel ou admin) podem arranchar em lote.
if (empty($_SESSION['usuario']['id'])) {
    responder('error', 'Sessão expirada, faça login novamente.', 401);
}

try {
    /*
     * Faz o PDO mostrar erros reais do banco.
     */
    $cnt->setAttribute(
        PDO::ATTR_ERRMODE,
        PDO::ERRMODE_EXCEPTION
    );

    $militares = $_POST['militares'] ?? [];
    $refeicoes = $_POST['refeicao'] ?? [];
    $diasTexto = trim($_POST['dias'] ?? '');

    /*
     * Garante que militares e refeições sejam arrays,
     * mesmo quando somente uma opção for selecionada.
     */
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
        responder(
            'error',
            'Selecione pelo menos um militar!'
        );
    }

    if ($diasTexto === '') {
        responder(
            'error',
            'Selecione pelo menos uma data!'
        );
    }

    if (count($refeicoes) === 0) {
        responder(
            'error',
            'Selecione pelo menos uma refeição!'
        );
    }

    /*
     * Aceita datas separadas por vírgula ou ponto e vírgula:
     * 03/07/2026,04/07/2026,05/07/2026
     */
    $diasRecebidos = preg_split(
        '/\s*[,;]\s*/',
        $diasTexto,
        -1,
        PREG_SPLIT_NO_EMPTY
    );

    $datas = [];

    foreach ($diasRecebidos as $dia) {
        $dia = trim($dia);

        $data = DateTime::createFromFormat(
            '!d/m/Y',
            $dia
        );

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
            responder(
                'error',
                'Data inválida: ' . $dia
            );
        }

        $dataBanco = $data->format('Y-m-d');

        if ($dataBanco <= date('Y-m-d')) {
            responder(
                'error',
                'Data não autorizada: ' . $dia
            );
        }

        $datas[] = $dataBanco;
    }

    $datas = array_values(array_unique($datas));

    $verificar = $cnt->prepare(
        "SELECT id
         FROM registros
         WHERE ident_militar = :militar
           AND tp = :refeicao
           AND data = :data
         LIMIT 1"
    );

    $inserir = $cnt->prepare(
        "INSERT IGNORE INTO registros
            (data, tp, ident_militar)
         VALUES
            (:data, :refeicao, :militar)"
    );

    $inseridos = 0;
    $existentes = 0;

    foreach ($datas as $dataBanco) {
        foreach ($refeicoes as $refeicao) {
            foreach ($militares as $militar) {

                $verificar->execute([
                    ':militar' => $militar,
                    ':refeicao' => $refeicao,
                    ':data' => $dataBanco
                ]);

                if ($verificar->fetchColumn() !== false) {
                    $existentes++;
                    continue;
                }

                $inserir->execute([
                    ':data' => $dataBanco,
                    ':refeicao' => $refeicao,
                    ':militar' => $militar
                ]);

                if ($inserir->rowCount() === 0) {
                    // Outro aparelho/usuário gravou a mesma refeição no mesmo instante.
                    $existentes++;
                    continue;
                }

                $registro = $cnt->lastInsertId();
                $inseridos++;

                /*
                 * O erro da auditoria não impede o arranchamento.
                 */
                try {
                    $usuarioId =
                        $_SESSION['usuario']['id'] ?? 0;

                    auditoria(
                        "ARRANCHAR",
                        $registro,
                        $cnt,
                        $usuarioId
                    );
                } catch (Throwable $erroAuditoria) {
                    error_log(
                        'Erro na auditoria: ' .
                        $erroAuditoria->getMessage()
                    );
                }
            }
        }
    }

    if ($inseridos === 0 && $existentes > 0) {
        responder(
            'success',
            'Nenhum registro novo foi criado. O militar já estava arranchado nas datas e refeições selecionadas.'
        );
    }

    responder(
        'success',
        'Arranchamento realizado! ' .
        $inseridos .
        ' registro(s) criado(s). ' .
        $existentes .
        ' já existente(s).'
    );

} catch (Throwable $erro) {
    error_log(
        'Erro ao arranchar: ' .
        $erro->getMessage()
    );

    responder(
        'error',
        'Erro ao salvar no banco: ' .
        $erro->getMessage(),
        500
    );
}
