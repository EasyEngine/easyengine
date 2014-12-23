"""EasyEngine core variable module"""
import platform
import socket
import configparser
import os
import sys


class EEVariables():
    """Intialization of core variables"""
    config = configparser.ConfigParser()
    config.read(os.path.expanduser("~")+'/.gitconfig')

    # EasyEngine core variables
    ee_platform_distro = platform.linux_distribution()[0]
    ee_platform_version = platform.linux_distribution()[1]
    ee_platform_codename = platform.linux_distribution()[2]

    # Get FQDN of system
    ee_fqdn = socket.getfqdn()

    # Get git user name and EMail
    try:
        ee_user = config['user']['name']
        ee_email = config['user']['email']
    except KeyError as e:
        print("Unable to find GIT user name and Email")
        sys.exit(1)

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
    ee_mysql_repo = ("deb http://repo.percona.com/apt {codename} main"
                     .format(codename=ee_platform_codename))
    ee_mysql = ["percona-server-server-5.6"]

    # Postfix repo and packages
    ee_postfix_repo = ""
    ee_postfix = ["postfix"]

    # Dovecot repo and packages
    ee_dovecot_repo = ("deb http://http.debian.net/debian-backports {codename}"
                       "-backports main".format(codename=ee_platform_codename))

    ee_dovecot = ["dovecot-core", "dovecot-imapd", "dovecot-pop3d",
                  "dovecot-lmtpd", "dovecot-mysql", "dovecot-sieve",
                  "dovecot-managesieved"]

    # Repo
    ee_repo_file = "ee-repo.list"
    ee_repo_file_path = ("/etc/apt/sources.list.d/" + ee_repo_file)

    def __init__(self):
        pass
