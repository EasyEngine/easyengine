"""EasyEngine core variable module"""
import platform
import socket
import configparser
import os
import sys
import psutil


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

    ee_webroot = '/var/www/'

    ee_php_user = 'www-data'

    # Get git user name and EMail
    try:
        ee_user = config['user']['name']
        ee_email = config['user']['email']
    except KeyError as e:
        print("Unable to find GIT user name and Email")
        sys.exit(1)

    # Get System RAM and SWAP details
    ee_ram = psutil.virtual_memory().total / (1024 * 1024)
    ee_swap = psutil.swap_memory().total / (1024 * 1024)

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
    ee_php = ["php5-fpm", "php5-curl", "php5-gd", "php5-cli", "php5-imap",
              "php5-mcrypt", "php5-xdebug", "php5-common", "php5-readline",
              "php5-mysql"]

    # MySQL repo and packages
    ee_mysql_repo = ("deb http://repo.percona.com/apt {codename} main"
                     .format(codename=ee_platform_codename))
    ee_mysql = ["percona-server-server-5.6"]

    # Postfix repo and packages
    ee_postfix_repo = ""
    ee_postfix = ["postfix"]

    # Mail repo and packages
    ee_mail_repo = ("deb http://http.debian.net/debian-backports {codename}"
                    "-backports main".format(codename=ee_platform_codename))

    ee_mail = ["dovecot-core", "dovecot-imapd", "dovecot-pop3d",
               "dovecot-lmtpd", "dovecot-mysql", "dovecot-sieve",
               "dovecot-managesieved", "postfix-mysql", "php5-cgi",
               "php5-json", "php-gettext"]

    # Mailscanner repo and packages
    ee_mailscanner_repo = ()
    ee_mailscanner = ["amavisd-new", "spamassassin", "clamav", "clamav-daemon",
                      "arj", "zoo", "nomarch", "cpio", "lzop",
                      "cabextract", "p7zip", "rpm", "unrar-free"]

    # Repo path
    ee_repo_file = "ee-repo.list"
    ee_repo_file_path = ("/etc/apt/sources.list.d/" + ee_repo_file)

    def __init__(self):
        pass
