"""EasyEngine core variable module"""
import platform


class EEVariables():
    """Intialization of core variables"""

    # EasyEngine core variables

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

    ee_platform_distro = platform.linux_distribution()[0]
    ee_platform_version = platform.linux_distribution()[1]
    ee_platform_codename = platform.linux_distribution()[2]

    # Repo
    ee_repo_file = "ee-repo.list"
    ee_repo_file_path = ("/etc/apt/sources.list.d/" + ee_repo_file)

    def __init__(self):
        pass
