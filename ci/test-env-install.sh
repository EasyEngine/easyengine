#!/usr/bin/env bash
function setup_test_requirements() {
    # Adding software-properties-common for add-apt-repository.
    apt-get install -y software-properties-common
    # Adding ondrej/php repository for installing php, this works for all ubuntu flavours.
    add-apt-repository -y ppa:ondrej/php
    apt-get update
    # Installing php-cli, which is the minimum requirement to run EasyEngine
    apt-get -y install php7.2-cli

    php_modules=( pcntl curl sqlite3 )
    if command -v php > /dev/null 2>&1; then
      # Reading the php version.
      default_php_version="$(readlink -f /usr/bin/php | gawk -F "php" '{ print $2}')"
      for module in "${php_modules[@]}"; do
        if ! php -m | grep $module >> $LOG_FILE 2>&1; then
          echo "$module not installed. Installing..."
          apt install -y php$default_php_version-$module
        else
          echo "$module is already installed"
        fi
      done
    fi
}

setup_test_requirements
