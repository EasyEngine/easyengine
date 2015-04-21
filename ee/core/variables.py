"""EasyEngine core variable module"""
import platform
import socket
import configparser
import os
import sys
import psutil
import datetime


class EEVariables():
    """Intialization of core variables"""

    # EasyEngine version
    ee_version = "3.1.1"

    # EasyEngine packages versions
    ee_wp_cli = "0.18.0"
    ee_adminer = "4.2.1"
    ee_roundcube = "1.1.1"
    ee_vimbadmin = "3.0.11"

    # Current date and time of System
    ee_date = datetime.datetime.now().strftime('%d%b%Y%H%M%S')

    # EasyEngine core variables
    ee_platform_distro = platform.linux_distribution()[0]
    ee_platform_version = platform.linux_distribution()[1]
    ee_platform_codename = os.popen("lsb_release -sc | tr -d \'\\n\'").read()

    # Get FQDN of system
    ee_fqdn = socket.getfqdn()

    # EasyEngien default webroot path
    ee_webroot = '/var/www/'

    # PHP5 user
    ee_php_user = 'www-data'

    # Get git user name and EMail
    config = configparser.ConfigParser()
    config.read(os.path.expanduser("~")+'/.gitconfig')
    try:
        ee_user = config['user']['name']
        ee_email = config['user']['email']
    except Exception as e:
        ee_user = input("Enter your name: ")
        ee_email = input("Enter your email: ")
        os.system("git config --global user.name {0}".format(ee_user))
        os.system("git config --global user.email {0}".format(ee_email))

    # Get System RAM and SWAP details
    ee_ram = psutil.virtual_memory().total / (1024 * 1024)
    ee_swap = psutil.swap_memory().total / (1024 * 1024)

    # MySQL hostname
    ee_mysql_host = ""
    config = configparser.RawConfigParser()
    cnfpath = os.path.expanduser("~")+"/.my.cnf"
    if [cnfpath] == config.read(cnfpath):
        try:
            ee_mysql_host = config.get('client', 'host')
        except configparser.NoOptionError as e:
            ee_mysql_host = "localhost"
    else:
        ee_mysql_host = "localhost"

    # EasyEngine stack installation varibales
    # Nginx repo and packages
    if ee_platform_distro == 'Ubuntu':
        ee_nginx_repo = "ppa:rtcamp/nginx"
        ee_nginx = ["nginx-custom", "nginx-common"]
    elif ee_platform_distro == 'debian':
        ee_nginx_repo = ("deb http://packages.dotdeb.org {codename} all"
                         .format(codename=ee_platform_codename))
        ee_nginx = ["nginx-extras", "nginx-common"]

    # PHP repo and packages
    if ee_platform_distro == 'Ubuntu':
        ee_php_repo = "ppa:ondrej/php5"
    elif ee_platform_codename == 'wheezy':
        ee_php_repo = ("deb http://packages.dotdeb.org {codename}-php55 all"
                       .format(codename=ee_platform_codename))
    ee_php = ["php5-fpm", "php5-curl", "php5-gd", "php5-imap",
              "php5-mcrypt", "php5-xdebug", "php5-common", "php5-readline",
              "php5-mysql", "php5-cli", "php5-memcache", "php5-imagick",
              "memcached", "graphviz"]

    # MySQL repo and packages
    if ee_platform_distro == 'Ubuntu':
        ee_mysql_repo = ("deb http://mirror.aarnet.edu.au/pub/MariaDB/repo/"
                         "10.0/ubuntu {codename} main"
                         .format(codename=ee_platform_codename))
    elif ee_platform_distro == 'debian':
        ee_mysql_repo = ("deb http://mirror.aarnet.edu.au/pub/MariaDB/repo/"
                         "10.0/debian {codename} main"
                         .format(codename=ee_platform_codename))
    ee_mysql = ["mariadb-server", "mysqltuner", "percona-toolkit"]

    # Postfix repo and packages
    ee_postfix_repo = ""
    ee_postfix = ["postfix"]

    # Mail repo and packages
    ee_mail_repo = ("deb http://http.debian.net/debian-backports {codename}"
                    "-backports main".format(codename=ee_platform_codename))

    ee_mail = ["dovecot-core", "dovecot-imapd", "dovecot-pop3d",
               "dovecot-lmtpd", "dovecot-mysql", "dovecot-sieve",
               "dovecot-managesieved", "postfix-mysql", "php5-cgi",
               "php-gettext", "php-pear"]

    # Mailscanner repo and packages
    ee_mailscanner_repo = ()
    ee_mailscanner = ["amavisd-new", "spamassassin", "clamav", "clamav-daemon",
                      "arj", "zoo", "nomarch", "lzop", "cabextract", "p7zip",
                      "rpm", "unrar-free"]

    # HHVM repo details
    # 12.04 requires boot repository
    if ee_platform_distro == 'Ubuntu':
        if ee_platform_codename == "precise":
            ee_boost_repo = ("ppa:mapnik/boost")

        ee_hhvm_repo = ("deb http://dl.hhvm.com/ubuntu {codename} main"
                        .format(codename=ee_platform_codename))
    else:
        ee_hhvm_repo = ("deb http://dl.hhvm.com/debian {codename} main"
                        .format(codename=ee_platform_codename))

    ee_hhvm = ["hhvm"]

    # Repo path
    ee_repo_file = "ee-repo.list"
    ee_repo_file_path = ("/etc/apt/sources.list.d/" + ee_repo_file)

    # Application dabase file path
    basedir = os.path.abspath(os.path.dirname('/var/lib/ee/'))
    ee_db_uri = 'sqlite:///' + os.path.join(basedir, 'ee.db')

    def __init__(self):
        pass
