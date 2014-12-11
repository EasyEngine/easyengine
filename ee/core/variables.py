"""EasyEngine core variable module"""


class EEVariables():
    """Intialization of core variables"""

    # EasyEngine core variables
    ee_version = "3.0.0"

    # EasyEngine stack installation varibales
    # Nginx repo and packages
    ee_nginx_repo = "ppa:rtcamp/nginx"
    ee_nginx = ["nginx-custom"]

    # PHP repo and packages
    ee_php_repo = "ppa:ondrej/php5"
    ee_php = ["php5-curl", "php5-gd", "php5-cli", "php5-fpm", "php5-imap",
              "php5-mcrypt", "php5-xdebug"]

    # MySQL repo and packages
    ee_mysql_repo = ""
    ee_mysql = ["mysql-server-5.6"]

    # Postfix repo and packages
    ee_postfix_repo = ""
    ee_postfix = ["postfix"]
