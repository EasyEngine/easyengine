# Function for site migration module

function ee_mod_migrate_data()
{
  rm -rf /ee-backup/$EE_DOMAIN && mkdir -p /ee-backup/$EE_DOMAIN/ && cd /ee-backup/$EE_DOMAIN/
  if [ "$EE_REMOTE_METHOD" == "ssh" ]; then
    # Lets FTP or rsync files
    rsync -avz --progress $USER@$HOST:$DIR/ /ee-backup/$EE_DOMAIN/
    if [ ! -f /ee-backup/$EE_DOMAIN/wp-config.php ]; then
      rsync -avz --progress $USER@$HOST:$DIR/../wp-config.php /ee-backup/$EE_DOMAIN/
    fi

  elif [ "$EE_REMOTE_METHOD" == "ftp" ]; then
    lftp -e "mirror -c $DIR" ftp://$USER@$HOST
    if [ ! -f /var/www/$SITE/htdocs/wp-config.php ]; then
      lftp -e "get $DIR/../wp-config.php" ftp://$USER@$HOST
    fi

  elif [ "$EE_REMOTE_METHOD" == "sftp" ]; then
    lftp -e "mirror -c $DIR" sftp://$USER@$HOST
    if [ ! -f /var/www/$SITE/htdocs/wp-config.php ]; then
      lftp -e "get $DIR/../wp-config.php" ftp://$USER@$HOST
    fi
  fi
}
