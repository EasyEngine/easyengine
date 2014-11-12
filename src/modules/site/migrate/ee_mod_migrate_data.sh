# Function for site migration module

function ee_mod_migrate_data()
{
  # Remove if any previous directory and create /ee-backup
  mkdir -p /ee-backup/$EE_DOMAIN/ && cd /ee-backup/$EE_DOMAIN/

  ee_lib_echo "Copying webroot from $EE_REMOTE_SERVER, please wait..."

  if [ "$EE_SITE_CREATE_OPTION" = "--wp" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ]; then
    EE_SITE_CONFIG=wp-config.php
  elif [ "$EE_SITE_CREATE_OPTION" = "--mysql" ]; then
    EE_SITE_CONFIG=ee-config.php
  fi

  # Copy webroot using ssh with the help of rsync
  if [ "$EE_REMOTE_METHOD" == "ssh" ]; then
    if [ "$EE_REMOTE_PASSWORD" != "" ]; then
      EE_MIGRATE_CMD1="rsync -avz --progress --rsh=\"sshpass -p$EE_REMOTE_PASSWORD ssh -l $EE_REMOTE_USER\" $EE_REMOTE_SERVER:$EE_REMOTE_PATH/ /ee-backup/$EE_DOMAIN/"
      EE_MIGRATE_CMD2="rsync -avz --progress --rsh=\"sshpass -p$EE_REMOTE_PASSWORD ssh -l $EE_REMOTE_USER\" $EE_REMOTE_SERVER:$EE_REMOTE_PATH/../$EE_SITE_CONFIG /ee-backup/$EE_DOMAIN/"
    else
      EE_MIGRATE_CMD1="rsync -avz --progress $EE_REMOTE_USER@$EE_REMOTE_SERVER:$EE_REMOTE_PATH/ /ee-backup/$EE_DOMAIN/"
      EE_MIGRATE_CMD2="rsync -avz --progress $EE_REMOTE_USER@$EE_REMOTE_SERVER:$EE_REMOTE_PATH/../$EE_SITE_CONFIG /ee-backup/$EE_DOMAIN/"
    fi
  elif [ "$EE_REMOTE_METHOD" == "sftp" ]; then
    if [ "$EE_REMOTE_PASSWORD" != "" ]; then
      EE_MIGRATE_CMD1="lftp -e \"mirror --verbose -c $EE_REMOTE_PATH /ee-backup/$EE_DOMAIN; quit\" -u \"$EE_REMOTE_USER,$EE_REMOTE_PASSWORD\" sftp://$EE_REMOTE_SERVER"
      EE_MIGRATE_CMD2="lftp -e \"get -c $EE_REMOTE_PATH/../$EE_SITE_CONFIG; quit\" -u \"$EE_REMOTE_USER,$EE_REMOTE_PASSWORD\" sftp://$EE_REMOTE_SERVER"
    else
      EE_MIGRATE_CMD1="lftp -e \"mirror --verbose -c $EE_REMOTE_PATH /ee-backup/$EE_DOMAIN; quit\" -u \"$EE_REMOTE_USER\" sftp://$EE_REMOTE_SERVER"
      EE_MIGRATE_CMD2="lftp -e \"get -c $EE_REMOTE_PATH/../$EE_SITE_CONFIG; quit\" -u \"$EE_REMOTE_USER\" ftp://$EE_REMOTE_SERVER"
    fi
  elif [ "$EE_REMOTE_METHOD" == "ftp" ]; then
    if [ "$EE_REMOTE_PASSWORD" != "" ]; then
      EE_MIGRATE_CMD1="lftp -e \"mirror --verbose -c $EE_REMOTE_PATH /ee-backup/$EE_DOMAIN; quit\" -u \"$EE_REMOTE_USER,$EE_REMOTE_PASSWORD\" ftp://$EE_REMOTE_SERVER"
      EE_MIGRATE_CMD2="lftp -e \"get -c $EE_REMOTE_PATH/../$EE_SITE_CONFIG; quit\" -u \"$EE_REMOTE_USER,$EE_REMOTE_PASSWORD\" ftp://$EE_REMOTE_SERVER"
    else
      EE_MIGRATE_CMD1="lftp -e \"mirror --verbose -c $EE_REMOTE_PATH /ee-backup/$EE_DOMAIN; quit\" -u \"$EE_REMOTE_USER\" ftp://$EE_REMOTE_SERVER"
      EE_MIGRATE_CMD2="lftp -e \"get -c $EE_REMOTE_PATH/../$EE_SITE_CONFIG; quit\" -u \"$EE_REMOTE_USER\" ftp://$EE_REMOTE_SERVER"
    fi
  fi

  eval $EE_MIGRATE_CMD1 \
  || ee_lib_error "Unable to migrate data using rsync, exit status = " $?
  if [ ! -f /ee-backup/$EE_DOMAIN/wp-config.php ] && [ "$EE_SITE_CREATE_OPTION" != "--html" ] && [ "$EE_SITE_CREATE_OPTION" != "--php" ]; then
    eval $EE_MIGRATE_CMD2 &>> $EE_COMMAND_LOG
  fi

}
