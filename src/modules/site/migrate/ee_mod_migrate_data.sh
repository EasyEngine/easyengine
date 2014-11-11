# Function for site migration module

function ee_mod_migrate_data()
{
  # Remove if any previous directory and create /ee-backup
  rm -rf /ee-backup/$EE_DOMAIN && mkdir -p /ee-backup/$EE_DOMAIN/ && cd /ee-backup/$EE_DOMAIN/

  ee_lib_echo "Copying webroot from $EE_REMOTE_SERVER, please wait..."
  # Copy webroot using ssh with the help of rsync
  if [ "$EE_REMOTE_METHOD" == "ssh" ]; then
    # Lets FTP or rsync files
    rsync -avz --progress $EE_REMOTE_USER@$EE_REMOTE_SERVER:$EE_REMOTE_PATH/ /ee-backup/$EE_DOMAIN/ \
    || ee_lib_error "Unable to migrate data using rsync, exit status = " $?
    if [ ! -f /ee-backup/$EE_DOMAIN/wp-config.php ] && [ "$EE_SITE_CREATE_OPTION" != "--html" ] && [ "$EE_SITE_CREATE_OPTION" != "--php" ] && [ "$EE_SITE_CREATE_OPTION" != "--mysql" ]; then
      rsync -avz --progress $EE_REMOTE_USER@$EE_REMOTE_SERVER:$EE_REMOTE_PATH/../wp-config.php /ee-backup/$EE_DOMAIN/ \
      || ee_lib_error "Unable to migrate data using rsync, exit status = " $?
    fi

  # Copy webroot using ftp with the help of lftp
  elif [ "$EE_REMOTE_METHOD" == "ftp" ]; then
    lftp -e "mirror --verbose -c $EE_REMOTE_PATH /ee-backup/$EE_DOMAIN; quit" -u "$EE_REMOTE_USER,$EE_REMOTE_PASSWORD" ftp://$EE_REMOTE_SERVER \
    || ee_lib_error "Unable to migrate data using ftp, exit status = " $?
    if [ ! -f /ee-backup/$EE_DOMAIN/wp-config.php ] && [ "$EE_SITE_CREATE_OPTION" != "--html" ] && [ "$EE_SITE_CREATE_OPTION" != "--php" ] && [ "$EE_SITE_CREATE_OPTION" != "--mysql" ]; then
      lftp -e "get -c $EE_REMOTE_PATH/../wp-config.php; quit" -u "$EE_REMOTE_USER,$EE_REMOTE_PASSWORD" ftp://$EE_REMOTE_SERVER \
      || ee_lib_error "Unable to migrate data using ftp, exit status = " $?
    fi

  # Copy webroot using sftp with the help of lftp
  elif [ "$EE_REMOTE_METHOD" == "sftp" ]; then
    lftp -e "mirror --verbose -c $EE_REMOTE_PATH /ee-backup/$EE_DOMAIN ; quit"; -u "$EE_REMOTE_USER,$EE_REMOTE_PASSWORD" sftp://$EE_REMOTE_SERVER \
    || ee_lib_error "Unable to migrate data using sftp, exit status = " $?
    if [ ! -f /ee-backup/$EE_DOMAIN/wp-config.php ] && [ "$EE_SITE_CREATE_OPTION" != "--html" ] && [ "$EE_SITE_CREATE_OPTION" != "--php" ] && [ ]"$EE_SITE_CREATE_OPTION" != "--mysql" ]; then
      lftp -e "get -c $EE_REMOTE_PATH/../wp-config.php; quit" -u "$EE_REMOTE_USER,$EE_REMOTE_PASSWORD" sftp://$EE_REMOTE_SERVER \
      || ee_lib_error "Unable to migrate data using lftp, exit status = " $?
    fi
  fi
}
