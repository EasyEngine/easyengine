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
    ee_version = "3.4.0"


    # EasyEngine packages versions
    ee_wp_cli = "0.21.1"
    ee_adminer = "4.2.1"
    ee_roundcube = "1.1.4"
    ee_vimbadmin = "3.0.12"

    # Get WPCLI path
    ee_wpcli_path = os.popen('which wp | tr "\n" " "').read()
    if ee_wpcli_path == '':
        ee_wpcli_path = '/usr/bin/wp '

    # Current date and time of System
    ee_date = datetime.datetime.now().strftime('%d%b%Y%H%M%S')

    # EasyEngine core variables
    ee_platform_distro = platform.linux_distribution()[0].lower()
    ee_platform_version = platform.linux_distribution()[1]
    ee_platform_codename = os.popen("lsb_release -sc | tr -d \'\\n\'").read()

    # Get timezone of system
    if os.path.isfile('/etc/timezone'):
        with open("/etc/timezone", "r") as tzfile:
            ee_timezone = tzfile.read().replace('\n', '')
            if ee_timezone == "Etc/UTC":
                ee_timezone = "UTC"
    else:
        ee_timezone = "UTC"

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
    if os.path.exists('/etc/mysql/conf.d/my.cnf'):
      cnfpath = "/etc/mysql/conf.d/my.cnf"
    else:
      cnfpath = os.path.expanduser("~")+"/.my.cnf"
    if [cnfpath] == config.read(cnfpath):
        try:
            ee_mysql_host = config.get('client', 'host')
        except configparser.NoOptionError as e:
            ee_mysql_host = "localhost"
    else:
        ee_mysql_host = "localhost"

    # EasyEngine stack installation variables
    # Nginx repo and packages
    if ee_platform_codename == 'precise':
        ee_nginx_repo = ("deb http://download.opensuse.org/repositories/home:"
                         "/rtCamp:/EasyEngine/xUbuntu_12.04/ /")
        ee_nginx_dev_repo = ("deb http://download.opensuse.org/repositories/home:"
                             "/rtCamp:/EasyEngine-dev/xUbuntu_12.04/ /")
    elif ee_platform_codename == 'trusty':
        ee_nginx_repo = ("deb http://download.opensuse.org/repositories/home:"
                         "/rtCamp:/EasyEngine/xUbuntu_14.04/ /")
        ee_nginx_dev_repo = ("deb http://download.opensuse.org/repositories/home:"
                             "/rtCamp:/EasyEngine-dev/xUbuntu_14.04/ /")
    elif ee_platform_codename == 'wheezy':
        ee_nginx_repo = ("deb http://download.opensuse.org/repositories/home:"
                         "/rtCamp:/EasyEngine/Debian_7.0/ /")
        ee_nginx_dev_repo = None
    elif ee_platform_codename == 'jessie':
        ee_nginx_repo = ("deb http://download.opensuse.org/repositories/home:"
                         "/rtCamp:/EasyEngine/Debian_8.0/ /")
        ee_nginx_dev_repo = ("deb http://download.opensuse.org/repositories/home:"
                             "/rtCamp:/EasyEngine-dev/Debian_8.0/ /")


    ee_nginx = ["nginx-custom", "nginx-common"]
    ee_nginx_dev = ["nginx-mainline", "nginx-common"]
    ee_nginx_key = '3050AC3CD2AE6F03'

    # PHP repo and packages
    if ee_platform_distro == 'ubuntu':
        ee_php_repo = "ppa:ondrej/php"
    elif ee_platform_codename == 'wheezy':
        ee_php_repo = ("deb http://packages.dotdeb.org {codename}-php all"
                       .format(codename=ee_platform_codename))
    ee_php = ["php7.0-fpm", "php7.0-curl", "php7.0-gd", "php7.0-imap",
              "php7.0-mcrypt", "php7.0-common", "php7.0-readline",
              "php7.0-mysql", "php7.0-cli", "php-memcache", "php-imagick",
              "memcached", "graphviz", "php-pear"]

    if ee_platform_codename == 'wheezy':
        ee_php = ee_php + ["php7.0-dev"]

    if ee_platform_distro == 'ubuntu' or ee_platform_codename == 'jessie':
        ee_php = ee_php + ["php7.0-xdebug"]

    # MySQL repo and packages
    if ee_platform_distro == 'ubuntu':
        ee_mysql_repo = ("deb http://sfo1.mirrors.digitalocean.com/mariadb/repo/"
                         "10.1/ubuntu {codename} main"
                         .format(codename=ee_platform_codename))
    elif ee_platform_distro == 'debian':
        ee_mysql_repo = ("deb http://sfo1.mirrors.digitalocean.com/mariadb/repo/"
                         "10.1/debian {codename} main"
                         .format(codename=ee_platform_codename))

    ee_mysql = ["mariadb-server", "percona-toolkit"]

    # Postfix repo and packages
    ee_postfix_repo = ""
    ee_postfix = ["postfix"]

    # Mail repo and packages
    ee_mail_repo = ("deb http://http.debian.net/debian-backports {codename}"
                    "-backports main".format(codename=ee_platform_codename))

    ee_mail = ["dovecot-core", "dovecot-imapd", "dovecot-pop3d",
               "dovecot-lmtpd", "dovecot-mysql", "dovecot-sieve",
               "dovecot-managesieved", "postfix-mysql", "php7.0-cgi",
               "php-gettext", "php-pear"]

    # Mailscanner repo and packages
    ee_mailscanner_repo = ()
    ee_mailscanner = ["amavisd-new", "spamassassin", "clamav", "clamav-daemon",
                      "arj", "zoo", "nomarch", "lzop", "cabextract", "p7zip",
                      "rpm", "unrar-free"]

    # HHVM repo details
    # 12.04 requires boot repository
    if ee_platform_distro == 'ubuntu':
        if ee_platform_codename == "precise":
            ee_boost_repo = ("ppa:mapnik/boost")

        ee_hhvm_repo = ("deb http://dl.hhvm.com/ubuntu {codename} main"
                        .format(codename=ee_platform_codename))
    else:
        ee_hhvm_repo = ("deb http://dl.hhvm.com/debian {codename} main"
                        .format(codename=ee_platform_codename))

    ee_hhvm = ["hhvm"]

    # Redis repo details
    if ee_platform_distro == 'ubuntu':
        ee_redis_repo = ("ppa:chris-lea/redis-server")

    else:
        ee_redis_repo = ("deb http://packages.dotdeb.org {codename} all"
                        .format(codename=ee_platform_codename))

    ee_redis = ['redis-server', 'php-redis']

    # Repo path
    ee_repo_file = "ee-repo.list"
    ee_repo_file_path = ("/etc/apt/sources.list.d/" + ee_repo_file)

    # Application dabase file path
    basedir = os.path.abspath(os.path.dirname('/var/lib/ee/'))
    ee_db_uri = 'sqlite:///' + os.path.join(basedir, 'ee.db')

    def __init__(self):
        pass
