#!/usr/bin/env bash
function setup_php() {
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

function setup_docker() {
    echo "Installing Docker"
    # Making sure wget and curl are installed.
    apt update && apt-get install wget curl -y
    # Running standard docker installation.
    wget --quiet get.docker.com -O docker-setup.sh
    sh docker-setup.sh
    
    echo "Installing Docker-Compose"
    # Running standard docker-compose installation.
    curl -L https://github.com/docker/compose/releases/download/1.21.2/docker-compose-$(uname -s)-$(uname -m) -o /usr/local/bin/docker-compose
    chmod +x /usr/local/bin/docker-compose
}

function setup_dependencies {
    setup_docker
    setup_php
}

setup_dependencies
