#!/bin/bash
# Backup diário do banco do Sistema de Arranchamento — chamado pelo cron (ver crontab do usuário
# arranchamento). Salva um dump compactado em ./backup, com log de cada execução, e apaga sozinho
# os backups automáticos com mais de $DIAS_PARA_MANTER dias (os manuais, como pre-upgrade-*.sql ou
# sisrancho-backup-DD-MM-AAAA-HH-MM.sql sem .gz, nunca são tocados por este script).
set -uo pipefail

PROJETO="/home/arranchamento/sistema-arranchamento"
DIR_BACKUP="/home/arranchamento/backups-banco"
LOG="$DIR_BACKUP/backup.log"
DIAS_PARA_MANTER=30

mkdir -p "$DIR_BACKUP"

log() {
	echo "$(date '+%Y-%m-%d %H:%M:%S') $1" >> "$LOG"
}

TIMESTAMP=$(date +"%d-%m-%Y-%H-%M")
ARQUIVO="$DIR_BACKUP/sisrancho-backup-$TIMESTAMP.sql.gz"

if docker exec sisrancho-db mysqldump -u root -ppassword --single-transaction --routines --triggers sisrancho 2>>"$LOG" | gzip > "$ARQUIVO"; then
	if [ -s "$ARQUIVO" ]; then
		log "Backup OK: $(basename "$ARQUIVO") ($(du -h "$ARQUIVO" | cut -f1))"
	else
		log "ERRO: backup ficou vazio, removendo $(basename "$ARQUIVO")"
		rm -f "$ARQUIVO"
		exit 1
	fi
else
	log "ERRO: mysqldump/gzip falhou (veja acima), removendo $(basename "$ARQUIVO")"
	rm -f "$ARQUIVO"
	exit 1
fi

REMOVIDOS=$(find "$DIR_BACKUP" -maxdepth 1 -name 'sisrancho-backup-*.sql.gz' -mtime +"$DIAS_PARA_MANTER" -print -delete)
if [ -n "$REMOVIDOS" ]; then
	log "Backups antigos removidos (>$DIAS_PARA_MANTER dias): $(echo "$REMOVIDOS" | xargs -n1 basename | tr '\n' ' ')"
fi

# Gira o log de erros do PHP (logs/php-erros.log) quando passar de 5 MB, mantendo 3 cópias antigas.
# Roda dentro do contêiner porque o arquivo pertence ao usuário do Apache (www-data).
GIRO=$(docker exec -u www-data sisrancho-php sh -c '
	f=/var/www/html/logs/php-erros.log
	if [ -f "$f" ] && [ "$(stat -c %s "$f")" -gt 5242880 ]; then
		rm -f "$f.3"; [ -f "$f.2" ] && mv "$f.2" "$f.3"; [ -f "$f.1" ] && mv "$f.1" "$f.2"
		mv "$f" "$f.1" && touch "$f" && echo girado
	fi' 2>&1)
if [ "$GIRO" = "girado" ]; then
	log "Log de erros do PHP passou de 5 MB e foi girado (php-erros.log.1)"
elif [ -n "$GIRO" ]; then
	log "AVISO: não foi possível girar o log de erros do PHP: $GIRO"
fi
