# Function for site migration module

function ee_mod_migrate_data()
{
  rm -rf /ee-backup/$EE_DOMAIN && mkdir -p /ee-backup/$EE_DOMAIN/ && cd /ee-backup/$EE_DOMAIN/
  if [ "$EE_REMOTE_METHOD" == "ssh" ]; then
    # Lets FTP or rsync files
    rsync -avz --progress $EE_REMOTE_USER@$EE_REMOTE_SERVER:$EE_REMOTE_PATH/ /ee-backup/$EE_DOMAIN/
    if [ ! -f /ee-backup/$EE_DOMAIN/wp-config.php ]; then
      rsync -avz --progress $EE_REMOTE_SERVER@$EE_REMOTE_SERVER:$EE_REMOTE_PATH/../wp-config.php /ee-backup/$EE_DOMAIN/
    fi

  elif [ "$EE_REMOTE_METHOD" == "ftp" ]; then
    lftp -e "mirror -c $EE_REMOTE_PATH" ftp://$EE_REMOTE_USER@$EE_REMOTE_SERVER
    if [ ! -f /ee-backup/$EE_DOMAIN/wp-config.php ]; then
      lftp -e "get $EE_REMOTE_PATH/../wp-config.php" ftp://$EE_REMOTE_USER@$EE_REMOTE_SERVER
    fi

  elif [ "$EE_REMOTE_METHOD" == "sftp" ]; then
    lftp -e "mirror -c $EE_REMOTE_PATH" sftp://$EE_REMOTE_USER@$EE_REMOTE_SERVER
    if [ ! -f /ee-backup/$EE_DOMAIN/wp-config.php ]; then
      lftp -e "get $EE_REMOTE_PATH/../wp-config.php" ftp://$EE_REMOTE_USER@$EE_REMOTE_SERVER
    fi
  fi
}
