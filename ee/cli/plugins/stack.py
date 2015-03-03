"""Stack Plugin for EasyEngine."""

from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.variables import EEVariables
from ee.core.aptget import EEAptGet
from ee.core.download import EEDownload
from ee.core.shellexec import EEShellExec
from ee.core.fileutils import EEFileUtils
from ee.core.apt_repo import EERepo
from ee.core.extract import EEExtract
from ee.core.mysql import EEMysql
from ee.core.addswap import EESwap
from ee.core.git import EEGit
from ee.core.checkfqdn import check_fqdn
from pynginxconfig import NginxConfig
from ee.core.services import EEService
from ee.core.variables import EEVariables
import random
import string
import configparser
import time
import shutil
import os
import pwd
import grp
import codecs
from ee.cli.plugins.stack_services import EEStackStatusController
from ee.cli.plugins.stack_migrate import EEStackMigrateController
from ee.core.logging import Log


def ee_stack_hook(app):
    # do something with the ``app`` object here.
    pass


class EEStackController(CementBaseController):
    class Meta:
        label = 'stack'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = 'Stack command manages stack operations'
        arguments = [
            (['--all'],
                dict(help='Install all stack', action='store_true')),
            (['--web'],
                dict(help='Install web stack', action='store_true')),
            (['--admin'],
                dict(help='Install admin tools stack', action='store_true')),
            (['--mail'],
                dict(help='Install mail server stack', action='store_true')),
            (['--mailscanner'],
                dict(help='Install mail scanner stack', action='store_true')),
            (['--nginx'],
                dict(help='Install Nginx stack', action='store_true')),
            (['--php'],
                dict(help='Install PHP stack', action='store_true')),
            (['--mysql'],
                dict(help='Install MySQL stack', action='store_true')),
            (['--postfix'],
                dict(help='Install Postfix stack', action='store_true')),
            (['--wpcli'],
                dict(help='Install WPCLI stack', action='store_true')),
            (['--phpmyadmin'],
                dict(help='Install PHPMyAdmin stack', action='store_true')),
            (['--adminer'],
                dict(help='Install Adminer stack', action='store_true')),
            (['--utils'],
                dict(help='Install Utils stack', action='store_true')),
            ]

    @expose(hide=True)
    def default(self):
        """default action of ee stack command"""
        self.app.args.print_help()

    @expose(hide=True)
    def pre_pref(self, apt_packages):
        """Pre settings to do before installation packages"""
        if set(EEVariables.ee_postfix).issubset(set(apt_packages)):
            Log.debug(self, "Pre-seeding Postfix")
            EEShellExec.cmd_exec(self, "echo \"postfix postfix"
                                 "/main_mailer_type string \'Internet Site\'\""
                                 " | debconf-set-selections")
            EEShellExec.cmd_exec(self, "echo \"postfix postfix/mailname string"
                                 " $(hostname -f)\" | debconf-set-selections")

        if set(EEVariables.ee_mysql).issubset(set(apt_packages)):
            Log.info(self, "Adding repository for MySQL, please wait ...")
            mysql_pref = ("Package: *\nPin: origin mirror.aarnet.edu.au"
                          "\nPin-Priority: 1000\n")
            with open('/etc/apt/preferences.d/'
                      'MariaDB.pref', 'w') as mysql_pref_file:
                mysql_pref_file.write(mysql_pref)
            EERepo.add(self, repo_url=EEVariables.ee_mysql_repo)
            Log.debug(self, 'Adding key for {0}'
                      .format(EEVariables.ee_mysql_repo))
            EERepo.add_key(self, '0xcbcb082a1bb943db',
                           keyserver="keyserver.ubuntu.com")
            chars = ''.join(random.sample(string.ascii_letters, 8))
            Log.debug(self, "Pre-seeding MySQL")
            Log.debug(self, "echo \"mariadb-server-10.0 "
                      "mysql-server/root_password "
                      "password \" | "
                      "debconf-set-selections")
            EEShellExec.cmd_exec(self, "echo \"mariadb-server-10.0 "
                                 "mysql-server/root_password "
                                 "password {chars}\" | "
                                 "debconf-set-selections".format(chars=chars),
                                 log=False)
            Log.debug(self, "echo \"mariadb-server-10.0 "
                      "mysql-server/root_password_again "
                      "password \" | "
                      "debconf-set-selections")
            EEShellExec.cmd_exec(self, "echo \"mariadb-server-10.0 "
                                 "mysql-server/root_password_again "
                                 "password {chars}\" | "
                                 "debconf-set-selections".format(chars=chars),
                                 log=False)
            mysql_config = """
            [client]
            user = root
            password = {chars}
            """.format(chars=chars)
            config = configparser.ConfigParser()
            config.read_string(mysql_config)
            Log.debug(self, 'Writting configuration into MySQL file')
            with open(os.path.expanduser("~")+'/.my.cnf', encoding='utf-8',
                      mode='w') as configfile:
                config.write(configfile)

        if set(EEVariables.ee_nginx).issubset(set(apt_packages)):
            Log.info(self, "Adding repository for NGINX, please wait ...")
            if EEVariables.ee_platform_distro == 'debian':
                Log.debug(self, 'Adding Dotdeb/nginx GPG key')
                EERepo.add(self, repo_url=EEVariables.ee_nginx_repo)
            else:
                EERepo.add(self, ppa=EEVariables.ee_nginx_repo)
                Log.debug(self, 'Adding ppa of Nginx')

        if set(EEVariables.ee_php).issubset(set(apt_packages)):
            Log.info(self, "Adding repository for PHP, please wait ...")
            if EEVariables.ee_platform_distro == 'debian':
                Log.debug(self, 'Adding repo_url of php for debian')
                EERepo.add(self, repo_url=EEVariables.ee_php_repo)
                Log.debug(self, 'Adding Dotdeb/php GPG key')
                EERepo.add_key(self, '89DF5277')
            else:
                Log.debug(self, 'Adding ppa for PHP')
                EERepo.add(self, ppa=EEVariables.ee_php_repo)

        if set(EEVariables.ee_mail).issubset(set(apt_packages)):
            if EEVariables.ee_platform_codename == 'squeeze':
                Log.info(self, "Adding repository for dovecot, "
                         "please wait ...")
                EERepo.add(self, repo_url=EEVariables.ee_dovecot_repo)
            Log.debug(self, 'Executing the command debconf-set-selections.')
            EEShellExec.cmd_exec(self, "echo \"dovecot-core dovecot-core/"
                                 "create-ssl-cert boolean yes\" "
                                 "| debconf-set-selections")
            EEShellExec.cmd_exec(self, "echo \"dovecot-core dovecot-core"
                                 "/ssl-cert-name string $(hostname -f)\""
                                 " | debconf-set-selections")

    @expose(hide=True)
    def post_pref(self, apt_packages, packages):
        """Post activity after installation of packages"""
        if len(apt_packages):
            if set(EEVariables.ee_postfix).issubset(set(apt_packages)):
                EEGit.add(self, ["/etc/postfix"],
                          msg="Adding Postfix into Git")
                EEService.reload_service(self, 'postfix')

            if set(EEVariables.ee_nginx).issubset(set(apt_packages)):
                if ((not EEShellExec.cmd_exec(self, "grep -Hr EasyEngine "
                   "/etc/nginx")) and os.path.isfile('/etc/nginx/nginx.conf')):
                    nc = NginxConfig()
                    Log.debug(self, 'Loading file /etc/nginx/nginx.conf ')
                    nc.loadf('/etc/nginx/nginx.conf')
                    nc.set('worker_processes', 'auto')
                    nc.append(('worker_rlimit_nofile', '100000'), position=2)
                    nc.remove(('events', ''))
                    nc.append({'name': 'events', 'param': '', 'value':
                              [('worker_connections', '4096'),
                               ('multi_accept', 'on')]}, position=4)
                    nc.set([('http',), 'keepalive_timeout'], '30')
                    Log.debug(self, "Writting nginx configuration to "
                              "file /etc/nginx/nginx.conf ")
                    nc.savef('/etc/nginx/nginx.conf')

                    # Custom Nginx configuration by EasyEngine
                    data = dict(version=EEVariables.ee_version)
                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/conf.d/ee-nginx.conf ')
                    ee_nginx = open('/etc/nginx/conf.d/ee-nginx.conf',
                                    encoding='utf-8', mode='w')
                    self.app.render((data), 'nginx-core.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    data = dict()
                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/conf.d/blockips.conf')
                    ee_nginx = open('/etc/nginx/conf.d/blockips.conf',
                                    encoding='utf-8', mode='w')
                    self.app.render((data), 'blockips.mustache', out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/conf.d/fastcgi.conf')
                    ee_nginx = open('/etc/nginx/conf.d/fastcgi.conf',
                                    encoding='utf-8', mode='w')
                    self.app.render((data), 'fastcgi.mustache', out=ee_nginx)
                    ee_nginx.close()

                    data = dict(php="9000", debug="9001")
                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/conf.d/upstream.conf ')
                    ee_nginx = open('/etc/nginx/conf.d/upstream.conf',
                                    encoding='utf-8', mode='w')
                    self.app.render((data), 'upstream.mustache', out=ee_nginx)
                    ee_nginx.close()

                    # Setup Nginx common directory
                    if not os.path.exists('/etc/nginx/common'):
                        Log.debug(self, 'Creating directory'
                                  '/etc/nginx/common')
                        os.makedirs('/etc/nginx/common')

                    data = dict(webroot=EEVariables.ee_webroot)
                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/acl.conf')
                    ee_nginx = open('/etc/nginx/common/acl.conf',
                                    encoding='utf-8', mode='w')
                    self.app.render((data), 'acl.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/locations.conf')
                    ee_nginx = open('/etc/nginx/common/locations.conf',
                                    encoding='utf-8', mode='w')
                    self.app.render((data), 'locations.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/ php.conf')
                    ee_nginx = open('/etc/nginx/common/php.conf',
                                    encoding='utf-8', mode='w')
                    self.app.render((data), 'php.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/w3tc.conf')
                    ee_nginx = open('/etc/nginx/common/w3tc.conf',
                                    encoding='utf-8', mode='w')
                    self.app.render((data), 'w3tc.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/wpcommon.conf')
                    ee_nginx = open('/etc/nginx/common/wpcommon.conf',
                                    encoding='utf-8', mode='w')
                    self.app.render((data), 'wpcommon.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/wpfc.conf')
                    ee_nginx = open('/etc/nginx/common/wpfc.conf',
                                    encoding='utf-8', mode='w')
                    self.app.render((data), 'wpfc.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/wpsc.conf')
                    ee_nginx = open('/etc/nginx/common/wpsc.conf',
                                    encoding='utf-8', mode='w')
                    self.app.render((data), 'wpsc.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/wpsubdir.conf')
                    ee_nginx = open('/etc/nginx/common/wpsubdir.conf',
                                    encoding='utf-8', mode='w')
                    self.app.render((data), 'wpsubdir.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    # 22222 port settings
                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/sites-available/'
                              '22222')
                    ee_nginx = open('/etc/nginx/sites-available/22222',
                                    encoding='utf-8', mode='w')
                    self.app.render((data), '22222.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    passwd = ''.join([random.choice
                                     (string.ascii_letters + string.digits)
                                     for n in range(6)])
                    EEShellExec.cmd_exec(self, "printf \"easyengine:"
                                         "$(openssl passwd -crypt "
                                         "{password} 2> /dev/null)\n\""
                                         "> /etc/nginx/htpasswd-ee 2>/dev/null"
                                         .format(password=passwd))

                    # Create Symbolic link for 22222
                    EEFileUtils.create_symlink(self, ['/etc/nginx/'
                                                      'sites-available/'
                                                      '22222',
                                                      '/etc/nginx/'
                                                      'sites-enabled/'
                                                      '22222'])
                    # Create log and cert folder and softlinks
                    if not os.path.exists('{0}22222/logs'
                                          .format(EEVariables.ee_webroot)):
                        Log.debug(self, "Creating directory "
                                  "{0}22222/logs "
                                  .format(EEVariables.ee_webroot))
                        os.makedirs('{0}22222/logs'
                                    .format(EEVariables.ee_webroot))

                    if not os.path.exists('{0}22222/cert'
                                          .format(EEVariables.ee_webroot)):
                        Log.debug(self, "Creating directory "
                                  "{0}22222/cert"
                                  .format(EEVariables.ee_webroot))
                        os.makedirs('{0}22222/cert'
                                    .format(EEVariables.ee_webroot))

                    EEFileUtils.create_symlink(self, ['/var/log/nginx/'
                                                      '22222.access.log',
                                                      '{0}22222/'
                                                      'logs/access.log'
                                               .format(EEVariables.ee_webroot)]
                                               )

                    EEFileUtils.create_symlink(self, ['/var/log/nginx/'
                                                      '22222.error.log',
                                                      '{0}22222/'
                                                      'logs/error.log'
                                               .format(EEVariables.ee_webroot)]
                                               )

                    EEShellExec.cmd_exec(self, "openssl genrsa -out "
                                         "{0}22222/cert/22222.key 2048"
                                         .format(EEVariables.ee_webroot))
                    EEShellExec.cmd_exec(self, "openssl req -new -batch -subj "
                                               "/commonName=127.0.0.1/ -key "
                                               "{0}22222/cert/22222.key "
                                               "-out {0}22222/cert/"
                                               "22222.csr"
                                         .format(EEVariables.ee_webroot))

                    EEFileUtils.mvfile(self, "{0}22222/cert/22222.key"
                                       .format(EEVariables.ee_webroot),
                                             "{0}22222/cert/"
                                             "22222.key.org"
                                       .format(EEVariables.ee_webroot))

                    EEShellExec.cmd_exec(self, "openssl rsa -in "
                                               "{0}22222/cert/"
                                               "22222.key.org -out "
                                               "{0}22222/cert/22222.key"
                                         .format(EEVariables.ee_webroot))

                    EEShellExec.cmd_exec(self, "openssl x509 -req -days 3652 "
                                               "-in {0}22222/cert/"
                                               "22222.csr -signkey {0}"
                                               "22222/cert/22222.key -out "
                                               "{0}22222/cert/22222.crt"
                                         .format(EEVariables.ee_webroot))
                    # Nginx Configation into GIT
                    EEGit.add(self,
                              ["/etc/nginx"], msg="Adding Nginx into Git")
                    EEService.reload_service(self, 'nginx')
                    self.msg = (self.msg + ["HTTP Auth User Name: easyengine"]
                                + ["HTTP Auth Password : {0}".format(passwd)])
            if set(EEVariables.ee_php).issubset(set(apt_packages)):
                # Create log directories
                if not os.path.exists('/var/log/php5/'):
                    Log.debug(self, 'Creating directory /var/log/php5/')
                    os.makedirs('/var/log/php5/')

                # Parse etc/php5/fpm/php.ini
                config = configparser.ConfigParser()
                Log.debug(self, "configuring php file /etc/php5/fpm/php.ini")
                config.read('/etc/php5/fpm/php.ini')
                config['PHP']['expose_php'] = 'Off'
                config['PHP']['post_max_size'] = '100M'
                config['PHP']['upload_max_filesize'] = '100M'
                config['PHP']['max_execution_time'] = '300'
                config['PHP']['date.timezone'] = time.tzname[time.daylight]
                with open('/etc/php5/fpm/php.ini',
                          encoding='utf-8', mode='w') as configfile:
                    Log.debug(self, "Writting php configuration into "
                              "/etc/php5/fpm/php.ini")
                    config.write(configfile)

                # Prase /etc/php5/fpm/php-fpm.conf
                config = configparser.ConfigParser()
                Log.debug(self, "configuring php file"
                          "/etc/php5/fpm/php-fpm.conf")
                config.read_file(codecs.open("/etc/php5/fpm/php-fpm.conf",
                                             "r", "utf8"))
                config['global']['error_log'] = '/var/log/php5/fpm.log'
                config.remove_option('global', 'include')
                config['global']['log_level'] = 'notice'
                config['global']['include'] = '/etc/php5/fpm/pool.d/*.conf'
                with codecs.open('/etc/php5/fpm/php-fpm.conf',
                                 encoding='utf-8', mode='w') as configfile:
                    Log.debug(self, "writting php5 configuration into "
                              "/etc/php5/fpm/php-fpm.conf")
                    config.write(configfile)

                # Parse /etc/php5/fpm/pool.d/www.conf
                config = configparser.ConfigParser()
                config.read_file(codecs.open('/etc/php5/fpm/pool.d/www.conf',
                                             "r", "utf8"))
                config['www']['ping.path'] = '/ping'
                config['www']['pm.status_path'] = '/status'
                config['www']['pm.max_requests'] = '500'
                config['www']['pm.max_children'] = '100'
                config['www']['pm.start_servers'] = '20'
                config['www']['pm.min_spare_servers'] = '10'
                config['www']['pm.max_spare_servers'] = '30'
                config['www']['request_terminate_timeout'] = '300'
                config['www']['pm'] = 'ondemand'
                config['www']['listen'] = '127.0.0.1:9000'
                with codecs.open('/etc/php5/fpm/pool.d/www.conf',
                                 encoding='utf-8', mode='w') as configfile:
                    Log.debug(self, "writting PHP5 configuration into "
                              "/etc/php5/fpm/pool.d/www.conf")
                    config.write(configfile)

                # Generate /etc/php5/fpm/pool.d/debug.conf
                EEFileUtils.copyfile(self, "/etc/php5/fpm/pool.d/www.conf",
                                     "/etc/php5/fpm/pool.d/debug.conf")
                EEFileUtils.searchreplace(self, "/etc/php5/fpm/pool.d/"
                                          "debug.conf", "[www]", "[debug]")
                config = configparser.ConfigParser()
                config.read('/etc/php5/fpm/pool.d/debug.conf')
                config['debug']['listen'] = '127.0.0.1:9001'
                config['debug']['rlimit_core'] = 'unlimited'
                with open('/etc/php5/fpm/pool.d/debug.conf',
                          encoding='utf-8', mode='w') as confifile:
                    Log.debug(self, "writting PHP5 configuration into "
                              "/etc/php5/fpm/pool.d/debug.conf")
                    config.write(confifile)

                with open("/etc/php5/fpm/pool.d/debug.conf",
                          encoding='utf-8', mode='a') as myfile:
                    myfile.write("php_admin_value[xdebug.profiler_output_dir] "
                                 "= /tmp/ \nphp_admin_value[xdebug.profiler_"
                                 "output_name] = cachegrind.out.%p-%H-%R "
                                 "\nphp_admin_flag[xdebug.profiler_enable"
                                 "_trigger] = on \nphp_admin_flag[xdebug."
                                 "profiler_enable] = off\n")

                # PHP and Debug pull configuration
                if not os.path.exists('{0}22222/htdocs/fpm/status/'
                                      .format(EEVariables.ee_webroot)):
                    Log.debug(self, 'Creating directory '
                              '{0}22222/htdocs/fpm/status/ '
                              .format(EEVariables.ee_webroot))
                    os.makedirs('{0}22222/htdocs/fpm/status/'
                                .format(EEVariables.ee_webroot))
                open('{0}22222/htdocs/fpm/status/debug'
                     .format(EEVariables.ee_webroot),
                     encoding='utf-8', mode='a').close()
                open('{0}22222/htdocs/fpm/status/php'
                     .format(EEVariables.ee_webroot),
                     encoding='utf-8', mode='a').close()

                # Write info.php
                if not os.path.exists('{0}22222/htdocs/php/'
                                      .format(EEVariables.ee_webroot)):
                    Log.debug(self, 'Creating directory '
                              '{0}22222/htdocs/php/ '
                              .format(EEVariables.ee_webroot))
                    os.makedirs('{0}22222/htdocs/php'
                                .format(EEVariables.ee_webroot))

                with open("{0}22222/htdocs/php/info.php"
                          .format(EEVariables.ee_webroot),
                          encoding='utf-8', mode='w') as myfile:
                    myfile.write("<?php\nphpinfo();\n?>")

                EEFileUtils.chown(self, "{0}22222"
                                  .format(EEVariables.ee_webroot),
                                  EEVariables.ee_php_user,
                                  EEVariables.ee_php_user, recursive=True)

                EEGit.add(self, ["/etc/php5"], msg="Adding PHP into Git")
                EEService.reload_service(self, 'php5-fpm')

            if set(EEVariables.ee_mysql).issubset(set(apt_packages)):
                # TODO: Currently we are using, we need to remove it in future
                # config = configparser.ConfigParser()
                # config.read('/etc/mysql/my.cnf')
                # config['mysqld']['wait_timeout'] = 30
                # config['mysqld']['interactive_timeout'] = 60
                # config['mysqld']['performance_schema'] = 0
                # with open('/etc/mysql/my.cnf', 'w') as configfile:
                #     config.write(configfile)
                if not os.path.isfile("/etc/mysql/my.cnf"):
                    config = ("[mysqld]\nwait_timeout = 30\n"
                              "interactive_timeout=60\nperformance_schema = 0")
                    config_file = open("/etc/mysql/my.cnf",
                                       encoding='utf-8', mode='w')
                    config_file.write(config)
                    config_file.close()
                else:
                    EEShellExec.cmd_exec(self, "sed -i \"/#max_connections/a "
                                         "wait_timeout = 30 \\n"
                                         "interactive_timeout = 60 \\n"
                                         "performance_schema = 0\" "
                                         "/etc/mysql/my.cnf")

                EEGit.add(self, ["/etc/mysql"], msg="Adding MySQL into Git")
                EEService.reload_service(self, 'mysql')

            if set(EEVariables.ee_mail).issubset(set(apt_packages)):
                Log.debug(self, "Adding user")
                EEShellExec.cmd_exec(self, "adduser --uid 5000 --home /var"
                                     "/vmail --disabled-password --gecos ''"
                                     " vmail")
                EEShellExec.cmd_exec(self, "openssl req -new -x509 -days 3650 "
                                     "-nodes -subj /commonName={HOSTNAME}"
                                     "/emailAddress={EMAIL} -out /etc/ssl"
                                     "/certs/dovecot."
                                     "pem -keyout /etc/ssl/private/dovecot.pem"
                                     .format(HOSTNAME=EEVariables.ee_fqdn,
                                             EMAIL=EEVariables.ee_email))
                Log.debug(self, "Setting Privileges to "
                          "/etc/ssl/private/dovecot.pem file ")
                EEShellExec.cmd_exec(self, "chmod 0600 /etc/ssl/private"
                                     "/dovecot.pem")

                # Custom Dovecot configuration by EasyEngine
                data = dict(email=EEVariables.ee_email)
                Log.debug(self, "Writting configuration into file"
                          "/etc/dovecot/conf.d/99-ee.conf ")
                ee_dovecot = open('/etc/dovecot/conf.d/99-ee.conf',
                                  encoding='utf-8', mode='w')
                self.app.render((data), 'dovecot.mustache', out=ee_dovecot)
                ee_dovecot.close()

                EEShellExec.cmd_exec(self, "sed -i \"s/\\!include "
                                     "auth-system.conf.ext/#\\!include "
                                     "auth-system.conf.ext/\" "
                                     "/etc/dovecot/conf.d/10-auth.conf")

                EEShellExec.cmd_exec(self, "sed -i \"s\'/etc/dovecot/"
                                     "dovecot.pem\'/etc/ssl/certs/dovecot.pem"
                                     "\'\" /etc/dovecot/conf.d/10-ssl.conf")
                EEShellExec.cmd_exec(self, "sed -i \"s\'/etc/dovecot/"
                                     "private/dovecot.pem\'/etc/ssl/private"
                                     "/dovecot.pem\'\" /etc/dovecot/conf.d/"
                                     "10-ssl.conf")

                # Custom Postfix configuration needed with Dovecot
                # Changes in master.cf
                # TODO: Find alternative for sed in Python
                EEShellExec.cmd_exec(self, "sed -i \'s/#submission/submission"
                                     "/\'"
                                     " /etc/postfix/master.cf")
                EEShellExec.cmd_exec(self, "sed -i \'s/#smtps/smtps/\'"
                                     " /etc/postfix/master.cf")

                EEShellExec.cmd_exec(self, "postconf -e \"smtpd_sasl_type = "
                                     "dovecot\"")
                EEShellExec.cmd_exec(self, "postconf -e \"smtpd_sasl_path = "
                                     "private/auth\"")
                EEShellExec.cmd_exec(self, "postconf -e \""
                                     "smtpd_sasl_auth_enable = "
                                     "yes\"")
                EEShellExec.cmd_exec(self, "postconf -e \""
                                           " smtpd_relay_restrictions ="
                                           " permit_sasl_authenticated, "
                                           " permit_mynetworks, "
                                           " reject_unauth_destination\"")

                EEShellExec.cmd_exec(self, "postconf -e \""
                                     "smtpd_tls_mandatory_"
                                     "protocols = !SSLv2,!SSLv3\"")
                EEShellExec.cmd_exec(self, "postconf -e \"smtp_tls_mandatory_"
                                     "protocols = !SSLv2,!SSLv3\"")
                EEShellExec.cmd_exec(self, "postconf -e \"smtpd_tls_protocols "
                                     " = !SSLv2,!SSLv3\"")
                EEShellExec.cmd_exec(self, "postconf -e \"smtp_tls_protocols "
                                     "= !SSLv2,!SSLv3\"")
                EEShellExec.cmd_exec(self, "postconf -e \"mydestination "
                                     "= localhost\"")
                EEShellExec.cmd_exec(self, "postconf -e \"virtual_transport "
                                     "= lmtp:unix:private/dovecot-lmtp\"")
                EEShellExec.cmd_exec(self, "postconf -e \"virtual_uid_maps "
                                     "= static:5000\"")
                EEShellExec.cmd_exec(self, "postconf -e \"virtual_gid_maps "
                                     "= static:5000\"")
                EEShellExec.cmd_exec(self, "postconf -e \""
                                     " virtual_mailbox_domains = "
                                     "mysql:/etc/postfix/mysql/virtual_"
                                     "domains_maps.cf\"")
                EEShellExec.cmd_exec(self, "postconf -e \"virtual_mailbox_maps"
                                     " = mysql:/etc/postfix/mysql/virtual_"
                                     "mailbox_maps.cf\"")
                EEShellExec.cmd_exec(self, "postconf -e \"virtual_alias_maps  "
                                     "= mysql:/etc/postfix/mysql/virtual_"
                                     "alias_maps.cf\"")
                EEShellExec.cmd_exec(self, "openssl req -new -x509 -days "
                                     " 3650 -nodes -subj /commonName="
                                     "{HOSTNAME}/emailAddress={EMAIL}"
                                     " -out /etc/ssl/certs/postfix.pem"
                                     " -keyout /etc/ssl/private/postfix.pem"
                                     .format(HOSTNAME=EEVariables.ee_fqdn,
                                             EMAIL=EEVariables.ee_email))
                EEShellExec.cmd_exec(self, "chmod 0600 /etc/ssl/private"
                                     "/postfix.pem")
                EEShellExec.cmd_exec(self, "postconf -e \"smtpd_tls_cert_file "
                                     "= /etc/ssl/certs/postfix.pem\"")
                EEShellExec.cmd_exec(self, "postconf -e \"smtpd_tls_key_file "
                                     "= /etc/ssl/private/postfix.pem\"")

                # Sieve configuration
                if not os.path.exists('/var/lib/dovecot/sieve/'):
                    Log.debug(self, 'Creating directory '
                              '/var/lib/dovecot/sieve/ ')
                    os.makedirs('/var/lib/dovecot/sieve/')

                # Custom sieve configuration by EasyEngine
                data = dict()
                Log.debug(self, "Writting configuration of EasyEngine into "
                          "file /var/lib/dovecot/sieve/default.sieve")
                ee_sieve = open('/var/lib/dovecot/sieve/default.sieve',
                                encoding='utf-8', mode='w')
                self.app.render((data), 'default-sieve.mustache',
                                out=ee_sieve)
                ee_sieve.close()

                # Compile sieve rules
                Log.debug(self, "Setting Privileges to dovecot ")
                # EEShellExec.cmd_exec(self, "chown -R vmail:vmail /var/lib"
                #                     "/dovecot")
                EEFileUtils.chown(self, "/var/lib/dovecot", 'vmail', 'vmail',
                                  recursive=True)
                EEShellExec.cmd_exec(self, "sievec /var/lib/dovecot/sieve/"
                                     "default.sieve")
                EEGit.add(self, ["/etc/postfix", "/etc/dovecot"],
                          msg="Installed mail server")
                EEService.restart_service(self, 'dovecot')
                EEService.reload_service(self, 'postfix')

            if set(EEVariables.ee_mailscanner).issubset(set(apt_packages)):
                # Set up Custom amavis configuration
                data = dict()
                Log.debug(self, "Configuring file /etc/amavis/conf.d"
                          "/15-content_filter_mode")
                ee_amavis = open('/etc/amavis/conf.d/15-content_filter_mode',
                                 encoding='utf-8', mode='w')
                self.app.render((data), '15-content_filter_mode.mustache',
                                out=ee_amavis)
                ee_amavis.close()

                # Amavis ViMbadmin configuration
                if os.path.isfile("/etc/postfix/mysql/virtual_alias_maps.cf"):
                    vm_host = os.popen("grep hosts /etc/postfix/mysql/virtual_"
                                       "alias_maps.cf | awk \'{ print $3 }\' |"
                                       " tr -d '\\n'").read()
                    vm_pass = os.popen("grep password /etc/postfix/mysql/"
                                       "virtual_alias_maps.cf | awk \'{ print "
                                       "$3 }\' | tr -d '\\n'").read()

                    data = dict(host=vm_host, password=vm_pass)
                    vm_config = open('/etc/amavis/conf.d/50-user',
                                     encoding='utf-8', mode='w')
                    self.app.render((data), '50-user.mustache', out=vm_config)
                    vm_config.close()

                # Amavis postfix configuration
                EEShellExec.cmd_exec(self, "postconf -e \"content_filter = "
                                     "smtp-amavis:[127.0.0.1]:10024\"")
                EEShellExec.cmd_exec(self, "sed -i \"s/1       pickup/1       "
                                     "pickup"
                                     "\\n        -o content_filter=\\n      -o"
                                     " receive_override_options=no_header_body"
                                     "_checks/\" /etc/postfix/master.cf")

                amavis_master = ("""smtp-amavis unix - - n - 2 smtp
    -o smtp_data_done_timeout=1200
    -o smtp_send_xforward_command=yes
    -o disable_dns_lookups=yes
    -o max_use=20
127.0.0.1:10025 inet n - n - - smtpd
    -o content_filter=
    -o smtpd_delay_reject=no
    -o smtpd_client_restrictions=permit_mynetworks,reject
    -o smtpd_helo_restrictions=
    -o smtpd_sender_restrictions=
    -o smtpd_recipient_restrictions=permit_mynetworks,reject
    -o smtpd_data_restrictions=reject_unauth_pipelining
    -o smtpd_end_of_data_restrictions=
    -o smtpd_restriction_classes=
    -o mynetworks=127.0.0.0/8
    -o smtpd_error_sleep_time=0
    -o smtpd_soft_error_limit=1001
    -o smtpd_hard_error_limit=1000
    -o smtpd_client_connection_count_limit=0
    -o smtpd_client_connection_rate_limit=0
    -o local_header_rewrite_clients=""")

                with open("/etc/postfix/master.cf",
                          encoding='utf-8', mode='a') as am_config:
                        am_config.write(amavis_master)

                # Amavis ClamAV configuration
                Log.debug(self, "Adding new user clamav amavis")
                EEShellExec.cmd_exec(self, "adduser clamav amavis")
                Log.debug(self, "Adding new user amavis clamav")
                EEShellExec.cmd_exec(self, "adduser amavis clamav")
                Log.debug(self, "Setting Privileges to /var/lib/amavis/tmp ")
                EEShellExec.cmd_exec(self, "chmod -R 775 /var/lib/amavis/tmp")

                # Update ClamAV database
                Log.debug(self, "Updating database")
                EEShellExec.cmd_exec(self, "freshclam")
                Log.debug(self, "Restarting clamav-daemon service")
                EEShellExec.cmd_exec(self, "service clamav-daemon restart")
                EEGit.add(self, ["/etc/amavis"], msg="Adding Amavis into Git")
                EEService.restart_service(self, 'dovecot')
                EEService.reload_service(self, 'postfix')
                EEService.restart_service(self, 'amavis')

        if len(packages):
            if any('/usr/bin/wp' == x[1] for x in packages):
                Log.debug(self, "Setting Privileges to /usr/bin/wp file ")
                EEShellExec.cmd_exec(self, "chmod +x /usr/bin/wp")
            if any('/tmp/pma.tar.gz' == x[1]
                    for x in packages):
                EEExtract.extract(self, '/tmp/pma.tar.gz', '/tmp/')
                Log.debug(self, 'Extracting file /tmp/pma.tar.gz to '
                          'loaction /tmp/')
                if not os.path.exists('{0}22222/htdocs/db'
                                      .format(EEVariables.ee_webroot)):
                    Log.debug(self, "Creating new  directory "
                              "{0}22222/htdocs/db"
                              .format(EEVariables.ee_webroot))
                    os.makedirs('{0}22222/htdocs/db'
                                .format(EEVariables.ee_webroot))
                shutil.move('/tmp/phpmyadmin-STABLE/',
                            '{0}22222/htdocs/db/pma/'
                            .format(EEVariables.ee_webroot))
                Log.debug(self, 'Setting Privileges of webroot permission to  '
                          '{0}22222/htdocs/db/pma file '
                          .format(EEVariables.ee_webroot))
                EEFileUtils.chown(self, '{0}22222'
                                  .format(EEVariables.ee_webroot),
                                  EEVariables.ee_php_user,
                                  EEVariables.ee_php_user,
                                  recursive=True)
            if any('/tmp/memcache.tar.gz' == x[1]
                    for x in packages):
                Log.debug(self, "Extracting memcache.tar.gz to location"
                          " {0}22222/htdocs/cache/memcache "
                          .format(EEVariables.ee_webroot))
                EEExtract.extract(self, '/tmp/memcache.tar.gz',
                                  '{0}22222/htdocs/cache/memcache'
                                  .format(EEVariables.ee_webroot))
                Log.debug(self, "Setting Privileges to "
                          "{0}22222/htdocs/cache/memcache file"
                          .format(EEVariables.ee_webroot))
                EEFileUtils.chown(self, '{0}22222'
                                  .format(EEVariables.ee_webroot),
                                  EEVariables.ee_php_user,
                                  EEVariables.ee_php_user,
                                  recursive=True)

            if any('/tmp/webgrind.tar.gz' == x[1]
                    for x in packages):
                Log.debug(self, "Extracting file webgrind.tar.gz to "
                          "location /tmp/ ")
                EEExtract.extract(self, '/tmp/webgrind.tar.gz', '/tmp/')
                if not os.path.exists('{0}22222/htdocs/php'
                                      .format(EEVariables.ee_webroot)):
                    Log.debug(self, "Creating directroy "
                              "{0}22222/htdocs/php"
                              .format(EEVariables.ee_webroot))
                    os.makedirs('{0}22222/htdocs/php'
                                .format(EEVariables.ee_webroot))
                shutil.move('/tmp/webgrind-master/',
                            '{0}22222/htdocs/php/webgrind'
                            .format(EEVariables.ee_webroot))
                EEShellExec.cmd_exec(self, "sed -i \"s\'/usr/local/bin/dot\'"
                                     "/usr/bin/dot\'\" {0}22222/htdocs/"
                                     "php/webgrind/config.php"
                                     .format(EEVariables.ee_webroot))
                Log.debug(self, "Setting Privileges of webroot permission to "
                          "{0}22222/htdocs/php/webgrind/ file "
                          .format(EEVariables.ee_webroot))
                EEFileUtils.chown(self, '{0}22222'
                                  .format(EEVariables.ee_webroot),
                                  EEVariables.ee_php_user,
                                  EEVariables.ee_php_user,
                                  recursive=True)

            if any('/tmp/anemometer.tar.gz' == x[1]
                    for x in packages):
                Log.debug(self, "Extracting file anemometer.tar.gz to "
                          "location /tmp/ ")
                EEExtract.extract(self, '/tmp/anemometer.tar.gz', '/tmp/')
                if not os.path.exists('{0}22222/htdocs/db/'
                                      .format(EEVariables.ee_webroot)):
                    Log.debug(self, "Creating directory")
                    os.makedirs('{0}22222/htdocs/db/'
                                .format(EEVariables.ee_webroot))
                shutil.move('/tmp/Anemometer-master',
                            '{0}22222/htdocs/db/anemometer'
                            .format(EEVariables.ee_webroot))
                chars = ''.join(random.sample(string.ascii_letters, 8))
                EEShellExec.cmd_exec(self, 'mysql < {0}22222/htdocs/db'
                                     '/anemometer/install.sql'
                                     .format(EEVariables.ee_webroot))
                EEMysql.execute(self, 'grant select on *.* to \'anemometer\''
                                '@\'{0}\''.format(self.app.config.get('mysql',
                                                  'grant-host')))
                Log.debug(self, "grant all on slow-query-log.*"
                          " to anemometer@root_user IDENTIFIED BY password ")
                EEMysql.execute(self, 'grant all on slow_query_log.* to'
                                '\'anemometer\'@\'{0}\' IDENTIFIED'
                                ' BY \'{1}\''.format(self.app.config.get(
                                                     'mysql', 'grant-host'),
                                                     chars),
                                errormsg="cannot grant privillages", log=False)

                # Custom Anemometer configuration
                Log.debug(self, "configration Anemometer")
                data = dict(host=EEVariables.ee_mysql_host, port='3306',
                            user='anemometer', password=chars)
                ee_anemometer = open('{0}22222/htdocs/db/anemometer'
                                     '/conf/config.inc.php'
                                     .format(EEVariables.ee_webroot),
                                     encoding='utf-8', mode='w')
                self.app.render((data), 'anemometer.mustache',
                                out=ee_anemometer)
                ee_anemometer.close()

            if any('/usr/bin/pt-query-advisor' == x[1]
                    for x in packages):
                EEShellExec.cmd_exec(self, "chmod +x /usr/bin/pt-query"
                                     "-advisor")

            if any('/tmp/vimbadmin.tar.gz' == x[1] for x in packages):
                # Extract ViMbAdmin
                Log.debug(self, "Extracting ViMbAdmin.tar.gz to "
                          "location /tmp/")
                EEExtract.extract(self, '/tmp/vimbadmin.tar.gz', '/tmp/')
                if not os.path.exists('{0}22222/htdocs/'
                                      .format(EEVariables.ee_webroot)):
                    Log.debug(self, "Creating directory "
                              "{0}22222/htdocs/"
                              .format(EEVariables.ee_webroot))
                    os.makedirs('{0}22222/htdocs/'
                                .format(EEVariables.ee_webroot))
                shutil.move('/tmp/ViMbAdmin-{0}/'
                            .format(EEVariables.ee_vimbadmin),
                            '{0}22222/htdocs/vimbadmin/'
                            .format(EEVariables.ee_webroot))

                # Donwload composer and install ViMbAdmin
                Log.debug(self, "Downloading composer "
                          "https://getcomposer.org/installer | php ")
                EEShellExec.cmd_exec(self, "cd {0}22222/htdocs"
                                     "/vimbadmin; curl"
                                     " -sS https://getcomposer.org/installer |"
                                     " php".format(EEVariables.ee_webroot))
                Log.debug(self, "installation of composer")
                EEShellExec.cmd_exec(self, "cd {0}22222/htdocs"
                                     "/vimbadmin && "
                                     "php composer.phar install --prefer-dist"
                                     " --no-dev && rm -f {1}22222/htdocs"
                                     "/vimbadmin/composer.phar"
                                     .format(EEVariables.ee_webroot,
                                             EEVariables.ee_webroot))

                # Configure vimbadmin database
                vm_passwd = ''.join(random.sample(string.ascii_letters, 8))
                Log.debug(self, "Creating vimbadmin database if not exist")
                EEMysql.execute(self, "create database if not exists"
                                      " vimbadmin")
                Log.debug(self, " grant all privileges on `vimbadmin`.* to"
                                " `vimbadmin`@`{0}` IDENTIFIED BY"
                                " ' '".format(self.app.config.get('mysql',
                                              'grant-host')))
                EEMysql.execute(self, "grant all privileges on `vimbadmin`.* "
                                " to `vimbadmin`@`{0}` IDENTIFIED BY"
                                " '{1}'".format(self.app.config.get('mysql',
                                                'grant-host'), vm_passwd),
                                errormsg="Cannot grant "
                                "user privileges", log=False)
                vm_salt = (''.join(random.sample(string.ascii_letters +
                                                 string.ascii_letters, 64)))

                # Custom Vimbadmin configuration by EasyEngine
                data = dict(salt=vm_salt, host=EEVariables.ee_mysql_host,
                            password=vm_passwd,
                            php_user=EEVariables.ee_php_user)
                Log.debug(self, 'Writting the ViMbAdmin configuration to '
                          'file {0}22222/htdocs/vimbadmin/application/'
                          'configs/application.ini'
                          .format(EEVariables.ee_webroot))
                ee_vmb = open('{0}22222/htdocs/vimbadmin/application/'
                              'configs/application.ini'
                              .format(EEVariables.ee_webroot),
                              encoding='utf-8', mode='w')
                self.app.render((data), 'vimbadmin.mustache',
                                out=ee_vmb)
                ee_vmb.close()

                shutil.copyfile("{0}22222/htdocs/vimbadmin/public/"
                                ".htaccess.dist"
                                .format(EEVariables.ee_webroot),
                                "{0}22222/htdocs/vimbadmin/public/"
                                ".htaccess".format(EEVariables.ee_webroot))
                Log.debug(self, "Executing command "
                          "{0}22222/htdocs/vimbadmin/bin"
                          "/doctrine2-cli.php orm:schema-tool:"
                          "create".format(EEVariables.ee_webroot))
                EEShellExec.cmd_exec(self, "{0}22222/htdocs/vimbadmin"
                                     "/bin/doctrine2-cli.php orm:schema-tool:"
                                     "create".format(EEVariables.ee_webroot))

                EEFileUtils.chown(self, '{0}22222'
                                  .format(EEVariables.ee_webroot),
                                  EEVariables.ee_php_user,
                                  EEVariables.ee_php_user,
                                  recursive=True)

                # Copy Dovecot and Postfix templates which are depednet on
                # Vimbadmin

                if not os.path.exists('/etc/postfix/mysql/'):
                    Log.debug(self, "Creating directory "
                              "/etc/postfix/mysql/")
                    os.makedirs('/etc/postfix/mysql/')

                if EEVariables.ee_mysql_host is "localhost":
                    data = dict(password=vm_passwd, host="127.0.0.1")
                else:
                    data = dict(password=vm_passwd,
                                host=EEVariables.ee_mysql_host)

                vm_config = open('/etc/postfix/mysql/virtual_alias_maps.cf',
                                 encoding='utf-8', mode='w')
                self.app.render((data), 'virtual_alias_maps.mustache',
                                out=vm_config)
                vm_config.close()

                Log.debug(self, "Writting configuration to  "
                          "/etc/postfix/mysql"
                          "/virtual_domains_maps.cf file")
                vm_config = open('/etc/postfix/mysql/virtual_domains_maps.cf',
                                 encoding='utf-8', mode='w')
                self.app.render((data), 'virtual_domains_maps.mustache',
                                out=vm_config)
                vm_config.close()

                Log.debug(self, "Writting configuration to "
                          "/etc/postfix/mysql"
                          "/virtual_mailbox_maps.cf file")
                vm_config = open('/etc/postfix/mysql/virtual_mailbox_maps.cf',
                                 encoding='utf-8', mode='w')
                self.app.render((data), 'virtual_mailbox_maps.mustache',
                                out=vm_config)
                vm_config.close()

                Log.debug(self, "Writting configration"
                                " to /etc/dovecot/dovecot-sql.conf.ext file ")
                vm_config = open('/etc/dovecot/dovecot-sql.conf.ext',
                                 encoding='utf-8', mode='w')
                self.app.render((data), 'dovecot-sql-conf.mustache',
                                out=vm_config)
                vm_config.close()

                # If Amavis is going to be installed then configure Vimabadmin
                # Amvis settings
                if set(EEVariables.ee_mailscanner).issubset(set(apt_packages)):
                    vm_config = open('/etc/amavis/conf.d/50-user',
                                     encoding='utf-8', mode='w')
                    self.app.render((data), '50-user.mustache',
                                    out=vm_config)
                    vm_config.close()
                EEService.restart_service(self, 'dovecot')
                EEService.reload_service(self, 'nginx')
                EEService.reload_service(self, 'php5-fpm')
                self.msg = (self.msg + ["Configure ViMbAdmin:\thttps://{0}:"
                            "22222/vimbadmin".format(EEVariables.ee_fqdn)]
                            + ["Security Salt: {0}".format(vm_salt)])

            if any('/tmp/roundcube.tar.gz' == x[1] for x in packages):
                # Extract RoundCubemail
                Log.debug(self, "Extracting file /tmp/roundcube.tar.gz "
                          "to location /tmp/ ")
                EEExtract.extract(self, '/tmp/roundcube.tar.gz', '/tmp/')
                if not os.path.exists('{0}roundcubemail'
                                      .format(EEVariables.ee_webroot)):
                    Log.debug(self, "Creating new directory "
                              " {0}roundcubemail/"
                              .format(EEVariables.ee_webroot))
                    os.makedirs('{0}roundcubemail/'
                                .format(EEVariables.ee_webroot))
                shutil.move('/tmp/roundcubemail-{0}/'
                            .format(EEVariables.ee_roundcube),
                            '{0}roundcubemail/htdocs'
                            .format(EEVariables.ee_webroot))

                # Install Roundcube depednet pear packages
                EEShellExec.cmd_exec(self, "pear install Mail_Mime Net_SMTP"
                                     " Mail_mimeDecode Net_IDNA2-beta "
                                     "Auth_SASL Net_Sieve Crypt_GPG")

                # Configure roundcube database
                rc_passwd = ''.join(random.sample(string.ascii_letters, 8))
                Log.debug(self, "Creating Database roundcubemail")
                EEMysql.execute(self, "create database if not exists "
                                " roundcubemail")
                Log.debug(self, "grant all privileges"
                                " on `roundcubemail`.* to "
                                " `roundcube`@`{0}` IDENTIFIED BY "
                                "' '".format(self.app.config.get(
                                             'mysql', 'grant-host')))
                EEMysql.execute(self, "grant all privileges"
                                " on `roundcubemail`.* to "
                                " `roundcube`@`{0}` IDENTIFIED BY "
                                "'{1}'".format(self.app.config.get(
                                               'mysql', 'grant-host'),
                                               rc_passwd))
                EEShellExec.cmd_exec(self, "mysql roundcubemail < {0}"
                                     "roundcubemail/htdocs/SQL/mysql"
                                     ".initial.sql"
                                     .format(EEVariables.ee_webroot))

                shutil.copyfile("{0}roundcubemail/htdocs/config/"
                                "config.inc.php.sample"
                                .format(EEVariables.ee_webroot),
                                "{0}roundcubemail/htdocs/config/"
                                "config.inc.php"
                                .format(EEVariables.ee_webroot))
                EEShellExec.cmd_exec(self, "sed -i \"s\'mysql://roundcube:"
                                     "pass@localhost/roundcubemail\'mysql://"
                                     "roundcube:{0}@{1}/"
                                     "roundcubemail\'\" {2}roundcubemail"
                                     "/htdocs/config/config."
                                     "inc.php"
                                     .format(rc_passwd,
                                             EEVariables.ee_mysql_host,
                                             EEVariables.ee_webroot))

                # Sieve plugin configuration in roundcube
                EEShellExec.cmd_exec(self, "bash -c \"sed -i \\\"s:\$config\["
                                     "\'plugins\'\] "
                                     "= array(:\$config\['plugins'\] =  "
                                     "array(\\n    \'sieverules\',:\\\" "
                                     "{0}roundcubemail/htdocs/config"
                                     .format(EEVariables.ee_webroot)
                                     + "/config.inc.php\"")
                EEShellExec.cmd_exec(self, "echo \"\$config['sieverules_port']"
                                     "=4190;\" >> {0}roundcubemail"
                                     .format(EEVariables.ee_webroot)
                                     + "/htdocs/config/config.inc.php")

                data = dict(site_name='webmail', www_domain='webmail',
                            static=False,
                            basic=True, wp=False, w3tc=False, wpfc=False,
                            wpsc=False, multisite=False, wpsubdir=False,
                            webroot=EEVariables.ee_webroot, ee_db_name='',
                            ee_db_user='', ee_db_pass='', ee_db_host='',
                            rc=True)

                Log.debug(self, 'Writting the nginx configuration for '
                          'RoundCubemail')
                ee_rc = open('/etc/nginx/sites-available/webmail',
                             encoding='utf-8', mode='w')
                self.app.render((data), 'virtualconf.mustache',
                                out=ee_rc)
                ee_rc.close()

                # Create Symbolic link for webmail
                EEFileUtils.create_symlink(self, ['/etc/nginx/sites-available'
                                                  '/webmail',
                                                  '/etc/nginx/sites-enabled/'
                                                  'webmail'])
                # Create log folder and softlinks
                if not os.path.exists('{0}roundcubemail/logs'
                                      .format(EEVariables.ee_webroot)):
                    os.makedirs('{0}roundcubemail/logs'
                                .format(EEVariables.ee_webroot))

                EEFileUtils.create_symlink(self, ['/var/log/nginx/'
                                                  'webmail.access.log',
                                                  '{0}roundcubemail/'
                                                  'logs/access.log'
                                           .format(EEVariables.ee_webroot)])

                EEFileUtils.create_symlink(self, ['/var/log/nginx/'
                                                  'webmail.error.log',
                                                  '{0}roundcubemail/'
                                                  'logs/error.log'
                                           .format(EEVariables.ee_webroot)])
                # Remove roundcube installer
                EEService.reload_service(self, 'nginx')
                EEFileUtils.remove(self, ["{0}roundcubemail/htdocs/installer"
                                   .format(EEVariables.ee_webroot)])
                EEFileUtils.chown(self, '{0}roundcubemail'
                                  .format(EEVariables.ee_webroot),
                                  EEVariables.ee_php_user,
                                  EEVariables.ee_php_user,
                                  recursive=True)

    @expose(help="Install packages")
    def install(self, packages=[], apt_packages=[], disp_msg=True):
        """Start installation of packages"""
        self.msg = []
        try:
            # Default action for stack installation
            if ((not self.app.pargs.web) and (not self.app.pargs.admin) and
               (not self.app.pargs.mail) and (not self.app.pargs.nginx) and
               (not self.app.pargs.php) and (not self.app.pargs.mysql) and
               (not self.app.pargs.postfix) and (not self.app.pargs.wpcli) and
               (not self.app.pargs.phpmyadmin) and
               (not self.app.pargs.adminer) and (not self.app.pargs.utils) and
               (not self.app.pargs.mailscanner) and (not self.app.pargs.all)):
                self.app.pargs.web = True
                self.app.pargs.admin = True

            if self.app.pargs.all:
                self.app.pargs.web = True
                self.app.pargs.admin = True
                self.app.pargs.mail = True

            if self.app.pargs.web:
                Log.debug(self, "Setting apt_packages variable for Nginx ,PHP"
                          " ,MySQL ")
                self.app.pargs.nginx = True
                self.app.pargs.php = True
                self.app.pargs.mysql = True
                self.app.pargs.wpcli = True
                self.app.pargs.postfix = True

            if self.app.pargs.admin:
                self.app.pargs.nginx = True
                self.app.pargs.php = True
                self.app.pargs.mysql = True
                self.app.pargs.adminer = True
                self.app.pargs.phpmyadmin = True
                self.app.pargs.utils = True

            if self.app.pargs.mail:
                self.app.pargs.nginx = True
                self.app.pargs.php = True
                self.app.pargs.mysql = True
                self.app.pargs.postfix = True

                if not EEAptGet.is_installed(self, 'dovecot-core'):
                    check_fqdn(self,
                               os.popen("hostname -f | tr -d '\n'").read())
                    Log.debug(self, "Setting apt_packages variable for mail")
                    apt_packages = apt_packages + EEVariables.ee_mail
                    packages = packages + [["https://github.com/opensolutions/"
                                            "ViMbAdmin/archive/{0}.tar.gz"
                                            .format(EEVariables.ee_vimbadmin),
                                            "/tmp/vimbadmin.tar.gz",
                                            "ViMbAdmin"],
                                           ["https://github.com/roundcube/"
                                            "roundcubemail/releases/download/"
                                            "{0}/roundcubemail-{0}.tar.gz"
                                            .format(EEVariables.ee_roundcube),
                                            "/tmp/roundcube.tar.gz",
                                            "Roundcube"]]

                    if EEVariables.ee_ram > 1024:
                        self.app.pargs.mailscanner = True
                    else:
                        Log.info(self, "System RAM is less than 1GB\nMail "
                                 "scanner packages are not going to install"
                                 " automatically")
                else:
                    Log.info(self, "Mail server is already installed")

            if self.app.pargs.nginx:
                Log.debug(self, "Setting apt_packages variable for Nginx")
                if not EEAptGet.is_installed(self, 'nginx-common'):
                    apt_packages = apt_packages + EEVariables.ee_nginx
                else:
                    Log.debug(self, "Nginx already installed")
                    Log.info(self, "Nginx already installed")
            if self.app.pargs.php:
                Log.debug(self, "Setting apt_packages variable for PHP")
                if not EEAptGet.is_installed(self, 'php5-fpm'):
                    apt_packages = apt_packages + EEVariables.ee_php
                else:
                    Log.debug(self, "PHP already installed")
                    Log.info(self, "PHP already installed")
            if self.app.pargs.mysql:
                Log.debug(self, "Setting apt_packages variable for MySQL")
                if not EEShellExec.cmd_exec(self, "mysqladmin ping"):
                    apt_packages = apt_packages + EEVariables.ee_mysql
                else:
                    Log.debug(self, "MySQL connection is already alive")
                    Log.info(self, "MySQL connection is already alive")
            if self.app.pargs.postfix:
                Log.debug(self, "Setting apt_packages variable for Postfix")
                if not EEAptGet.is_installed(self, 'postfix'):
                    apt_packages = apt_packages + EEVariables.ee_postfix
                else:
                    Log.debug(self, "Postfix is already installed")
                    Log.info(self, "Postfix is already installed")
            if self.app.pargs.wpcli:
                Log.debug(self, "Setting packages variable for WP-CLI")
                if not EEShellExec.cmd_exec(self, "which wp"):
                    packages = packages + [["https://github.com/wp-cli/wp-cli/"
                                            "releases/download/v{0}/"
                                            "wp-cli-{0}.phar"
                                            "".format(EEVariables.ee_wp_cli),
                                            "/usr/bin/wp",
                                            "WP-CLI"]]
                else:
                    Log.debug(self, "WP-CLI is already installed")
                    Log.info(self, "WP-CLI is already installed")
            if self.app.pargs.phpmyadmin:
                Log.debug(self, "Setting packages varible for phpMyAdmin ")
                packages = packages + [["https://github.com/phpmyadmin/"
                                        "phpmyadmin/archive/STABLE.tar.gz",
                                        "/tmp/pma.tar.gz", "phpMyAdmin"]]

            if self.app.pargs.adminer:
                Log.debug(self, "Setting packages variable for Adminer ")
                packages = packages + [["http://downloads.sourceforge.net/"
                                        "adminer/adminer-{0}.php"
                                        "".format(EEVariables.ee_adminer),
                                        "{0}22222/"
                                        "htdocs/db/adminer/index.php"
                                        .format(EEVariables.ee_webroot),
                                        "Adminer"]]

            if self.app.pargs.mailscanner:
                if not EEAptGet.is_installed(self, 'amavisd-new'):
                    if (EEAptGet.is_installed(self, 'dovecot-core') or
                       self.app.pargs.mail):
                        apt_packages = (apt_packages +
                                        EEVariables.ee_mailscanner)
                    else:
                        Log.error(self, "Failed to find installed Dovecot")
                else:
                    Log.error(self, "Mail scanner already installed")

            if self.app.pargs.utils:
                Log.debug(self, "Setting packages variable for utils")
                packages = packages + [["http://phpmemcacheadmin.googlecode"
                                        ".com/files/phpMemcachedAdmin-1.2.2"
                                        "-r262.tar.gz", '/tmp/memcache.tar.gz',
                                        'phpMemcachedAdmin'],
                                       ["https://raw.githubusercontent.com"
                                        "/rtCamp/eeadmin/master/cache/nginx/"
                                        "clean.php",
                                        "{0}22222/htdocs/cache/"
                                        "nginx/clean.php"
                                        .format(EEVariables.ee_webroot),
                                        "clean.php"],
                                       ["https://raw.github.com/rlerdorf/"
                                        "opcache-status/master/opcache.php",
                                        "{0}22222/htdocs/cache/"
                                        "opcache/opcache.php"
                                        .format(EEVariables.ee_webroot),
                                        "opcache.php"],
                                       ["https://raw.github.com/amnuts/"
                                        "opcache-gui/master/index.php",
                                        "{0}22222/htdocs/"
                                        "cache/opcache/opgui.php"
                                        .format(EEVariables.ee_webroot),
                                        "Opgui"],
                                       ["https://gist.github.com/ck-on/4959032"
                                        "/raw/0b871b345fd6cfcd6d2be030c1f33d1"
                                        "ad6a475cb/ocp.php",
                                        "{0}22222/htdocs/cache/"
                                        "opcache/ocp.php"
                                        .format(EEVariables.ee_webroot),
                                        "OCP.php"],
                                       ["https://github.com/jokkedk/webgrind/"
                                        "archive/master.tar.gz",
                                        '/tmp/webgrind.tar.gz', 'Webgrind'],
                                       ["http://bazaar.launchpad.net/~"
                                        "percona-toolkit-dev/percona-toolkit/"
                                        "2.1/download/head:/ptquerydigest-"
                                        "20110624220137-or26tn4"
                                        "expb9ul2a-16/pt-query-digest",
                                        "/usr/bin/pt-query-advisor",
                                        "pt-query-advisor"],
                                       ["https://github.com/box/Anemometer/"
                                        "archive/master.tar.gz",
                                        '/tmp/anemometer.tar.gz', 'Anemometer']
                                       ]
        except Exception as e:
            pass

        if len(apt_packages) or len(packages):
            Log.debug(self, "Calling pre_pref")
            self.pre_pref(apt_packages)
            if len(apt_packages):
                EESwap.add(self)
                Log.info(self, "Updating apt-cache, please wait ...")
                EEAptGet.update(self)
                Log.info(self, "Installing packages, please wait ...")
                EEAptGet.install(self, apt_packages)
            if len(packages):
                Log.debug(self, "Downloading following: {0}".format(packages))
                EEDownload.download(self, packages)
            Log.debug(self, "Calling post_pref")
            self.post_pref(apt_packages, packages)
            if disp_msg:
                if len(self.msg):
                    for msg in self.msg:
                        Log.info(self, Log.ENDC + msg)
                Log.info(self, "Successfully installed packages")
            else:
                return self.msg

    @expose(help="Remove packages")
    def remove(self):
        """Start removal of packages"""
        apt_packages = []
        packages = []

        # Default action for stack remove
        if ((not self.app.pargs.web) and (not self.app.pargs.admin) and
           (not self.app.pargs.mail) and (not self.app.pargs.nginx) and
           (not self.app.pargs.php) and (not self.app.pargs.mysql) and
           (not self.app.pargs.postfix) and (not self.app.pargs.wpcli) and
           (not self.app.pargs.phpmyadmin) and
           (not self.app.pargs.adminer) and (not self.app.pargs.utils) and
           (not self.app.pargs.mailscanner) and (not self.app.pargs.all)):
            self.app.pargs.web = True
            self.app.pargs.admin = True

        if self.app.pargs.all:
            self.app.pargs.web = True
            self.app.pargs.admin = True
            self.app.pargs.mail = True

        if self.app.pargs.web:
            self.app.pargs.nginx = True
            self.app.pargs.php = True
            self.app.pargs.mysql = True
            self.app.pargs.wpcli = True
            self.app.pargs.postfix = True

        if self.app.pargs.admin:
            self.app.pargs.adminer = True
            self.app.pargs.phpmyadmin = True
            self.app.pargs.utils = True

        if self.app.pargs.mail:
            Log.debug(self, "Removing mail server packages")
            apt_packages = apt_packages + EEVariables.ee_mail
            apt_packages = apt_packages + EEVariables.ee_mailscanner
            packages = packages + ["{0}22222/htdocs/vimbadmin"
                                   .format(EEVariables.ee_webroot),
                                   "{0}roundcubemail"
                                   .format(EEVariables.ee_webroot)]
            if EEShellExec.cmd_exec(self, "mysqladmin ping"):
                EEMysql.execute(self, "drop database IF EXISTS vimbadmin")
                EEMysql.execute(self, "drop database IF EXISTS roundcubemail")

        if self.app.pargs.mailscanner:
            apt_packages = (apt_packages + EEVariables.ee_mailscanner)

        if self.app.pargs.nginx:
            Log.debug(self, "Removing apt_packages variable of Nginx")
            apt_packages = apt_packages + EEVariables.ee_nginx
        if self.app.pargs.php:
            Log.debug(self, "Removing apt_packages variable of PHP")
            apt_packages = apt_packages + EEVariables.ee_php
        if self.app.pargs.mysql:
            Log.debug(self, "Removing apt_packages variable of MySQL")
            apt_packages = apt_packages + EEVariables.ee_mysql
        if self.app.pargs.postfix:
            Log.debug(self, "Removing apt_packages variable of Postfix")
            apt_packages = apt_packages + EEVariables.ee_postfix
        if self.app.pargs.wpcli:
            Log.debug(self, "Removing package variable of WPCLI ")
            packages = packages + ['/usr/bin/wp']
        if self.app.pargs.phpmyadmin:
            Log.debug(self, "Removing package variable of phpMyAdmin ")
            packages = packages + ['{0}22222/htdocs/db/pma'
                                   .format(EEVariables.ee_webroot)]
        if self.app.pargs.adminer:
            Log.debug(self, "Removing package variable of Adminer ")
            packages = packages + ['{0}22222/htdocs/db/adminer'
                                   .format(EEVariables.ee_webroot)]
        if self.app.pargs.utils:
            Log.debug(self, "Removing package variable of utils ")
            packages = packages + ['{0}22222/htdocs/php/webgrind/'
                                   .format(EEVariables.ee_webroot),
                                   '{0}22222/htdocs/cache/opcache'
                                   .format(EEVariables.ee_webroot),
                                   '{0}22222/htdocs/cache/nginx/'
                                   'clean.php'.format(EEVariables.ee_webroot),
                                   '{0}22222/htdocs/cache/memcache'
                                   .format(EEVariables.ee_webroot),
                                   '/usr/bin/pt-query-advisor',
                                   '{0}22222/htdocs/db/anemometer'
                                   .format(EEVariables.ee_webroot)]

        if len(apt_packages):
            Log.debug(self, "Removing apt_packages")
            Log.info(self, "Uninstalling packages, please wait ...")
            EEAptGet.remove(self, apt_packages)
            EEAptGet.auto_remove(self)
        if len(packages):
            EEFileUtils.remove(self, packages)
            EEAptGet.auto_remove(self)
        Log.info(self, "Successfully removed packages")

    @expose(help="Purge packages")
    def purge(self):
        """Start purging of packages"""
        apt_packages = []
        packages = []

        # Default action for stack purge
        if ((not self.app.pargs.web) and (not self.app.pargs.admin) and
           (not self.app.pargs.mail) and (not self.app.pargs.nginx) and
           (not self.app.pargs.php) and (not self.app.pargs.mysql) and
           (not self.app.pargs.postfix) and (not self.app.pargs.wpcli) and
           (not self.app.pargs.phpmyadmin) and
           (not self.app.pargs.adminer) and (not self.app.pargs.utils) and
           (not self.app.pargs.mailscanner) and (not self.app.pargs.all)):
            self.app.pargs.web = True
            self.app.pargs.admin = True

        if self.app.pargs.all:
            self.app.pargs.web = True
            self.app.pargs.admin = True
            self.app.pargs.mail = True

        if self.app.pargs.web:
            self.app.pargs.nginx = True
            self.app.pargs.php = True
            self.app.pargs.mysql = True
            self.app.pargs.wpcli = True
            self.app.pargs.postfix = True

        if self.app.pargs.admin:
            self.app.pargs.adminer = True
            self.app.pargs.phpmyadmin = True
            self.app.pargs.utils = True

        if self.app.pargs.mail:
            Log.debug(self, "Removing mail server packages")
            apt_packages = apt_packages + EEVariables.ee_mail
            apt_packages = apt_packages + EEVariables.ee_mailscanner
            packages = packages + ["{0}22222/htdocs/vimbadmin"
                                   .format(EEVariables.ee_webroot),
                                   "{0}roundcubemail"
                                   .format(EEVariables.ee_webroot)]
            if EEShellExec.cmd_exec(self, "mysqladmin ping"):
                EEMysql.execute(self, "drop database IF EXISTS vimbadmin")
                EEMysql.execute(self, "drop database IF EXISTS roundcubemail")

        if self.app.pargs.mailscanner:
            apt_packages = (apt_packages + EEVariables.ee_mailscanner)

        if self.app.pargs.nginx:
            Log.debug(self, "Purge apt_packages variable of Nginx")
            apt_packages = apt_packages + EEVariables.ee_nginx
        if self.app.pargs.php:
            Log.debug(self, "Purge apt_packages variable PHP")
            apt_packages = apt_packages + EEVariables.ee_php
        if self.app.pargs.mysql:
            Log.debug(self, "Purge apt_packages variable MySQL")
            apt_packages = apt_packages + EEVariables.ee_mysql
        if self.app.pargs.postfix:
            Log.debug(self, "Purge apt_packages variable PostFix")
            apt_packages = apt_packages + EEVariables.ee_postfix
        if self.app.pargs.wpcli:
            Log.debug(self, "Purge package variable WPCLI")
            packages = packages + ['/usr/bin/wp']
        if self.app.pargs.phpmyadmin:
            packages = packages + ['{0}22222/htdocs/db/pma'.
                                   format(EEVariables.ee_webroot)]
            Log.debug(self, "Purge package variable phpMyAdmin")
        if self.app.pargs.adminer:
            Log.debug(self, "Purge  package variable Adminer")
            packages = packages + ['{0}22222/htdocs/db/adminer'
                                   .format(EEVariables.ee_webroot)]
        if self.app.pargs.utils:
            Log.debug(self, "Purge package variable utils")
            packages = packages + ['{0}22222/htdocs/php/webgrind/'
                                   .format(EEVariables.ee_webroot),
                                   '{0}22222/htdocs/cache/opcache'
                                   .format(EEVariables.ee_webroot),
                                   '{0}22222/htdocs/cache/nginx/'
                                   'clean.php'.format(EEVariables.ee_webroot),
                                   '{0}22222/htdocs/cache/memcache'
                                   .format(EEVariables.ee_webroot),
                                   '/usr/bin/pt-query-advisor',
                                   '{0}22222/htdocs/db/anemometer'
                                   .format(EEVariables.ee_webroot)
                                   ]

        if len(apt_packages):
            Log.info(self, "Uninstalling packages, please wait ...")
            EEAptGet.remove(self, apt_packages, purge=True)
            EEAptGet.auto_remove(self)
        if len(packages):
            EEFileUtils.remove(self, packages)
            EEAptGet.auto_remove(self)
        Log.info(self, "Successfully purged packages")


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EEStackController)
    handler.register(EEStackStatusController)
    handler.register(EEStackMigrateController)

    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', ee_stack_hook)
