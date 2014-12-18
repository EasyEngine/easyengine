"""EasyEngine core variable module"""
import platform


class EEVariables():
    """Intialization of core variables"""

    # EasyEngine core variables
    ee_platform_distro = platform.linux_distribution()[0]
    ee_platform_version = platform.linux_distribution()[1]
    ee_platform_codename = platform.linux_distribution()[2]

    # EasyEngine stack installation varibales
    # Nginx repo and packages
    if ee_platform_distro == 'Ubuntu':
        ee_nginx_repo = "ppa:rtcamp/nginx"
    elif ee_platform_distro == 'Debian':
        ee_nginx_repo = ("deb http://packages.dotdeb.org {codename} all"
                         .format(codename=ee_platform_codename))
    ee_nginx = ["nginx-custom"]

    # PHP repo and packages
    if ee_platform_distro == 'Ubuntu':
        ee_php_repo = "ppa:ondrej/php5"
    elif ee_platform_codename == 'squeeze':
        ee_php_repo = ("deb http://packages.dotdeb.org {codename}-php54 all"
                       .format(codename=ee_platform_codename))
    elif ee_platform_codename == 'wheezy':
        ee_php_repo = ("deb http://packages.dotdeb.org {codename}-php55 all"
                       .format(codename=ee_platform_codename))
    ee_php = ["php5-curl", "php5-gd", "php5-cli", "php5-fpm", "php5-imap",
              "php5-mcrypt", "php5-xdebug"]

    # MySQL repo and packages
    ee_mysql_repo = ""
    ee_mysql = ["percona-server-server-5.6"]

    # Postfix repo and packages
    ee_postfix_repo = ""
    ee_postfix = ["postfix"]

    # Repo
    ee_repo_file = "ee-repo.list"
    ee_repo_file_path = ("/etc/apt/sources.list.d/" + ee_repo_file)

    def __init__(self):
        pass
