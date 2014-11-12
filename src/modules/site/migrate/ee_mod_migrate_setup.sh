# Function to Copy data from backup to webroot

function ee_mod_migrate_setup()
{
  # Copy data
  ee_lib_echo "Copying data from /ee-backup to webroot, please wait..."
  cp -a /ee-backup/$EE_DOMAIN/* /var/www/$EE_DOMAIN/htdocs/ \
  || ee_lib_error "Unable to copy backup data to site webroot, exit status = " $?

  # Setup Database for WordPress site
  if [ "$EE_SITE_CREATE_OPTION" = "--wp" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdir" ] || [ "$EE_SITE_CREATE_OPTION" = "--wpsubdomain" ]; then
    mv /var/www/$EE_DOMAIN/htdocs/wp-config.php /var/www/$EE_DOMAIN/

    if [ "$EE_MYSQL_PATH" != "" ]; then
      ee_lib_echo "Setting up Database, please wait..."
      ee_mod_setup_database

      # Replace old database values with new values
      sed -i "s/DB_NAME.*/DB_NAME', '$EE_DB_NAME');/g" /var/www/$EE_DOMAIN/wp-config.php
      sed -i "s/DB_user.*/DB_USER', '$EE_DB_USER');/g" /var/www/$EE_DOMAIN/wp-config.php
      sed -i "s/DB_HOST.*/DB_HOST', '$EE_MYSQL_HOST');/g" /var/www/$EE_DOMAIN/wp-config.php
      sed -i "s/DB_PASSWORD.*/DB_PASSWORD', '$EE_DB_PASS');/g" /var/www/$EE_DOMAIN/wp-config.php

      # Import database
      ee_lib_echo "Importing database, please wait..."
      pv $EE_MYSQL_PATH | mysql $EE_DB_NAME \
      || ee_lib_error "Unable to import database, exit status = " $?
    fi
  fi

  # Setup database for MySQL site
  if [ "$EE_SITE_CREATE_OPTION" = "--mysql" ]; then
    if [ -f /var/www/$EE_DOMAIN/htdocs/ee-config.php ]; then
      rm /var/www/$EE_DOMAIN/htdocs/ee-config.php
    fi
    if [ "$EE_MYSQL_PATH" != "" ]; then

      EE_DB_NAME=$(grep DB_NAME /var/www/$EE_DOMAIN/ee-config.php | cut -d"'" -f4)
      # Import database
      ee_lib_echo "Importing database, please wait..."
      pv $EE_MYSQL_PATH | mysql $EE_DB_NAME \
      || ee_lib_error "Unable to import database, exit status = " $?
    fi
  fi

  # Fix webroot permission
  chown -R www-data:www-data /var/www/$EE_DOMAIN/ \
  || ee_lib_error "Unable to change webroot permission, exit status = " $?

}
