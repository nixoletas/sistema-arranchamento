#!/bin/bash

# Obter a data e hora atuais no formato DD-MM-AAAA-HH-MM
TIMESTAMP=$(date +"%d-%m-%Y-%H-%M")

# Nome do arquivo de backup
BACKUP_FILE="sisrancho-backup-$TIMESTAMP.sql"

# Executar o comando mysqldump dentro do contêiner e salvar o backup no host
docker exec -it sisrancho-db mysqldump -u root -ppassword sisrancho > ./$BACKUP_FILE

# Mensagem de sucesso
echo "Backup concluído com sucesso: $BACKUP_FILE"