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
from pynginxconfig import NginxConfig
from ee.core.services import EEService
import random
import string
import configparser
import time
import shutil
import os
import pwd
import grp
from ee.cli.plugins.stack_services import EEStackStatusController
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
            (['--web'],
                dict(help='Install web stack', action='store_true')),
            (['--admin'],
                dict(help='Install admin tools stack', action='store_true')),
            (['--mail'],
                dict(help='Install mail server stack', action='store_true')),
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
        # TODO Default action for ee stack command
        self.app.args.print_help()

    @expose(hide=True)
    def pre_pref(self, apt_packages):
        if set(EEVariables.ee_postfix).issubset(set(apt_packages)):
            Log.info(self, "Pre-seeding Postfix")
            EEShellExec.cmd_exec(self, "echo \"postfix postfix"
                                 "/main_mailer_type string \'Internet Site\'\""
                                 " | debconf-set-selections")
            EEShellExec.cmd_exec(self, "echo \"postfix postfix/mailname string"
                                 " $(hostname -f)\" | debconf-set-selections")

        if set(EEVariables.ee_mysql).issubset(set(apt_packages)):
            Log.info(self, "Adding repository for MySQL")
            EERepo.add(self, repo_url=EEVariables.ee_mysql_repo)
            Log.debug(self, 'Adding key for {0}'
                      .format(EEVariables.ee_mysql_repo))
            EERepo.add_key(self, '1C4CBDCDCD2EFD2A')
            chars = ''.join(random.sample(string.ascii_letters, 8))
            Log.info(self, "Pre-seeding MySQL")
            EEShellExec.cmd_exec(self, "echo \"percona-server-server-5.6 "
                                 "percona-server-server/root_password "
                                 "password {chars}\" | "
                                 "debconf-set-selections".format(chars=chars))
            EEShellExec.cmd_exec(self, "echo \"percona-server-server-5.6 "
                                 "percona-server-server/root_password_again "
                                 "password {chars}\" | "
                                 "debconf-set-selections".format(chars=chars))
            mysql_config = """
            [client]
            user = root
            password = {chars}
            """.format(chars=chars)
            config = configparser.ConfigParser()
            config.read_string(mysql_config)
            Log.debug(self, 'Writting configuration into MySQL file')
            with open(os.path.expanduser("~")+'/.my.cnf', 'w') as configfile:
                config.write(configfile)

        if set(EEVariables.ee_nginx).issubset(set(apt_packages)):
            Log.info(self, "Adding repository for Nginx")
            if EEVariables.ee_platform_distro == 'debian':
                Log.debug(self, 'Adding Dotdeb/nginx GPG key')
                EERepo.add(self, repo_url=EEVariables.ee_nginx_repo)
            else:
                EERepo.add(self, ppa=EEVariables.ee_nginx_repo)
                Log.debug(self, 'Adding ppa of Nginx')

        if set(EEVariables.ee_php).issubset(set(apt_packages)):
            Log.info(self, "Adding repository for PHP")
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
                Log.info(self, "Adding repository for dovecot ")
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
        if len(apt_packages):
            if set(EEVariables.ee_postfix).issubset(set(apt_packages)):
                EEGit.add(self, ["/etc/postfix"],
                          msg="Adding Postfix into Git")
                EEService.reload_service(self, 'postfix')

            if set(EEVariables.ee_nginx).issubset(set(apt_packages)):
                if ((not os.path.isfile('/etc/nginx/conf.d/ee-nginx.conf')) and
                   os.path.isfile('/etc/nginx/nginx.conf')):
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
                    ee_nginx = open('/etc/nginx/conf.d/ee-nginx.conf', 'w')
                    self.app.render((data), 'nginx-core.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    data = dict()
                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/conf.d/blockips.conf')
                    ee_nginx = open('/etc/nginx/conf.d/blockips.conf', 'w')
                    self.app.render((data), 'blockips.mustache', out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/conf.d/fastcgi.conf')
                    ee_nginx = open('/etc/nginx/conf.d/fastcgi.conf', 'w')
                    self.app.render((data), 'fastcgi.mustache', out=ee_nginx)
                    ee_nginx.close()

                    data = dict(php="9000", debug="9001")
                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/conf.d/upstream.conf ')
                    ee_nginx = open('/etc/nginx/conf.d/upstream.conf', 'w')
                    self.app.render((data), 'upstream.mustache', out=ee_nginx)
                    ee_nginx.close()

                    # Setup Nginx common directory
                    if not os.path.exists('/etc/nginx/common'):
                        Log.debug(self, 'Creating directory'
                                  '/etc/nginx/common')
                        os.makedirs('/etc/nginx/common')

                    data = dict()
                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/acl.conf')
                    ee_nginx = open('/etc/nginx/common/acl.conf', 'w')
                    self.app.render((data), 'acl.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/locations.conf')
                    ee_nginx = open('/etc/nginx/common/locations.conf', 'w')
                    self.app.render((data), 'locations.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/ php.conf')
                    ee_nginx = open('/etc/nginx/common/php.conf', 'w')
                    self.app.render((data), 'php.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/w3tc.conf')
                    ee_nginx = open('/etc/nginx/common/w3tc.conf', 'w')
                    self.app.render((data), 'w3tc.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/wpcommon.conf')
                    ee_nginx = open('/etc/nginx/common/wpcommon.conf', 'w')
                    self.app.render((data), 'wpcommon.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/wpfc.conf')
                    ee_nginx = open('/etc/nginx/common/wpfc.conf', 'w')
                    self.app.render((data), 'wpfc.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/wpsc.conf')
                    ee_nginx = open('/etc/nginx/common/wpsc.conf', 'w')
                    self.app.render((data), 'wpsc.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/common/wpsubdir.conf')
                    ee_nginx = open('/etc/nginx/common/wpsubdir.conf', 'w')
                    self.app.render((data), 'wpsubdir.mustache',
                                    out=ee_nginx)
                    ee_nginx.close()

                    # 22222 port settings
                    Log.debug(self, 'Writting the nginx configuration to '
                              'file /etc/nginx/sites-available/'
                              '22222.conf')
                    ee_nginx = open('/etc/nginx/sites-available/22222.conf',
                                    'w')
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
                                                      '22222.conf',
                                                      '/etc/nginx/'
                                                      'sites-enabled/'
                                                      '22222.conf'])
                    # Create log and cert folder and softlinks
                    if not os.path.exists('/var/www/22222/logs'):
                        Log.debug(self, "Creating directory "
                                  "/var/www/22222/logs ")
                        os.makedirs('/var/www/22222/logs')

                    if not os.path.exists('/var/www/22222/cert'):
                        Log.debug(self, "Creating directory "
                                  "/var/www/22222/cert")
                        os.makedirs('/var/www/22222/cert')

                    EEFileUtils.create_symlink(self, ['/var/log/nginx/'
                                                      '22222.access.log',
                                                      '/var/www/22222/'
                                                      'logs/access.log'])

                    EEFileUtils.create_symlink(self, ['/var/log/nginx/'
                                                      '22222.error.log',
                                                      '/var/www/22222/'
                                                      'logs/error.log'])

                    EEShellExec.cmd_exec(self, "openssl genrsa -out "
                                         "/var/www/22222/cert/22222.key 2048")
                    EEShellExec.cmd_exec(self, "openssl req -new -batch -subj "
                                               "/commonName=127.0.0.1/ -key "
                                               "/var/www/22222/cert/22222.key "
                                               "-out /var/www/22222/cert/"
                                               "22222.csr")

                    EEFileUtils.mvfile(self, "/var/www/22222/cert/22222.key",
                                             "/var/www/22222/cert/"
                                             "22222.key.org")

                    EEShellExec.cmd_exec(self, "openssl rsa -in "
                                               "/var/www/22222/cert/"
                                               "22222.key.org -out "
                                               "/var/www/22222/cert/22222.key")

                    EEShellExec.cmd_exec(self, "openssl x509 -req -days 3652 "
                                               "-in /var/www/22222/cert/"
                                               "22222.csr -signkey /var/www/"
                                               "22222/cert/22222.key -out "
                                               "/var/www/22222/cert/22222.crt")
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
                with open('/etc/php5/fpm/php.ini', 'w') as configfile:
                    Log.debug(self, "Writting php configuration into "
                              "/etc/php5/fpm/php.ini")
                    config.write(configfile)

                # Prase /etc/php5/fpm/php-fpm.conf
                config = configparser.ConfigParser()
                config.read('/etc/php5/fpm/php-fpm.conf')
                config['global']['error_log'] = '/var/log/php5/fpm.log'
                config['global']['log_level'] = 'notice'
                with open('/etc/php5/fpm/php-fpm.conf', 'w') as configfile:
                    Log.debug(self, "writting php5 configuration into "
                              "/etc/php5/fpm/php-fpm.conf")
                    config.write(configfile)

                # Parse /etc/php5/fpm/pool.d/www.conf
                config = configparser.ConfigParser()
                config.read('/etc/php5/fpm/pool.d/www.conf')
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
                with open('/etc/php5/fpm/pool.d/www.conf', 'w') as configfile:
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
                with open('/etc/php5/fpm/pool.d/debug.conf', 'w') as confifile:
                    Log.debug(self, "writting PHP5 configuration into "
                              "/etc/php5/fpm/pool.d/debug.conf")
                    config.write(confifile)

                with open("/etc/php5/fpm/pool.d/debug.conf", "a") as myfile:
                    myfile.write("php_admin_value[xdebug.profiler_output_dir] "
                                 "= /tmp/ \nphp_admin_value[xdebug.profiler_"
                                 "output_name] = cachegrind.out.%p-%H-%R "
                                 "\nphp_admin_flag[xdebug.profiler_enable"
                                 "_trigger] = on \nphp_admin_flag[xdebug."
                                 "profiler_enable] = off\n")

                # PHP and Debug pull configuration
                if not os.path.exists('/var/www/22222/htdocs/fpm/status/'):
                    Log.debug(self, 'Creating directory '
                              '/var/www/22222/htdocs/fpm/status/ ')
                    os.makedirs('/var/www/22222/htdocs/fpm/status/')
                open('/var/www/22222/htdocs/fpm/status/debug', 'a').close()
                open('/var/www/22222/htdocs/fpm/status/php', 'a').close()

                # Write info.php
                if not os.path.exists('/var/www/22222/htdocs/php/'):
                    Log.debug(self, 'Creating directory '
                              '/var/www/22222/htdocs/php/ ')
                    os.makedirs('/var/www/22222/htdocs/php')

                with open("/var/www/22222/htdocs/php/info.php", "w") as myfile:
                    myfile.write("<?php\nphpinfo();\n?>")

                EEFileUtils.chown(self, "/var/www/22222", 'www-data',
                                  'www-data', recursive=True)

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
                    config_file = open("/etc/mysql/my.cnf", "w")
                    config_file.write(config)
                    config_file.close()
                else:
                    EEShellExec.cmd_exec(self, "sed -i \"/#max_connections/a "
                                         "wait_timeout = 30 \\n"
                                         "interactive_timeout = 60 \\n"
                                         "performance_schema = 0\" "
                                         "/etc/mysql/my.cnf")

                EEGit.add(self, ["/etc/mysql"], msg="Adding Nginx into Git")
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
                data = dict()
                Log.debug(self, "Writting configuration into file"
                          "/etc/dovecot/conf.d/99-ee.conf ")
                ee_dovecot = open('/etc/dovecot/conf.d/99-ee.conf', 'w')
                self.app.render((data), 'dovecot.mustache', out=ee_dovecot)
                ee_dovecot.close()

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
                                     " mysql:/etc/postfix/mysql/virtual_"
                                     " domains_maps.cf\"")
                EEShellExec.cmd_exec(self, "postconf -e \"virtual_mailbox_maps"
                                     " = mysql:/etc/postfix/mysql/virtual_"
                                     "mailbox_maps.cf\"")
                EEShellExec.cmd_exec(self, "postconf -e \"virtual_alias_maps  "
                                     "= mysql:/etc/postfix/mysql/virtual_"
                                     " alias_maps.cf\"")
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
                                     " = /etc/ssl/private/postfix.pem\"")

                # Sieve configuration
                if not os.path.exists('/var/lib/dovecot/sieve/'):
                    Log.debug(self, 'Creating directory '
                              '/var/lib/dovecot/sieve/ ')
                    os.makedirs('/var/lib/dovecot/sieve/')

                # Custom sieve configuration by EasyEngine
                data = dict()
                Log.debug(self, "Writting configuration of EasyEngine into "
                          "file /var/lib/dovecot/sieve/default.sieve")
                ee_sieve = open('/var/lib/dovecot/sieve/default.sieve', 'w')
                self.app.render((data), 'default-sieve.mustache',
                                out=ee_sieve)
                ee_sieve.close()

                # Compile sieve rules
                Log.debug(self, "Setting Privileges to dovecot ")
                # EEShellExec.cmd_exec(self, "chown -R vmail:vmail /var/lib"
                #                     "/dovecot")
                EEFileUtils.chown(self, "/var/lig/dovecot", 'vmail', 'vmail',
                                  recursive=True)
                EEShellExec.cmd_exec(self, "sievec /var/lib/dovecot/sieve/"
                                     "default.sieve")
                EEGit.add(self, ["/etc/postfix", "/etc/dovecot"],
                          msg="Installed mail server")
                EEService.reload_service(self, 'dovecot')
                EEService.reload_service(self, 'postfix')

            if set(EEVariables.ee_mailscanner).issubset(set(apt_packages)):
                # Set up Custom amavis configuration
                data = dict()
                Log.debug(self, "Configuring file /etc/amavis/conf.d"
                          "/15-content_filter_mode")
                ee_amavis = open('/etc/amavis/conf.d/15-content_filter_mode',
                                 'w')
                self.app.render((data), '15-content_filter_mode.mustache',
                                out=ee_amavis)
                ee_amavis.close()

                # Amavis postfix configuration
                EEShellExec.cmd_exec(self, "postconf -e \"content_filter = "
                                     "smtp-amavis:[127.0.0.1]:10024\"")
                EEShellExec.cmd_exec(self, "sed -i \"s/1       pickup/1       "
                                     "pickup"
                                     "\n        -o content_filter=\n        -o"
                                     " receive_override_options=no_header_body"
                                     "_checks/\" /etc/postfix/master.cf")

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
                EEGit.add(self, ["/etc/amavis"], msg="Adding Amvis into Git")
                EEService.reload_service(self, 'dovecot')
                EEService.reload_service(self, 'postfix')
                EEService.reload_service(self, 'amavis')

        if len(packages):
            if any('/usr/bin/wp' == x[1] for x in packages):
                Log.debug(self, "Setting Privileges to /usr/bin/wp file ")
                EEShellExec.cmd_exec(self, "chmod +x /usr/bin/wp")
            if any('/tmp/pma.tar.gz' == x[1]
                    for x in packages):
                EEExtract.extract(self, '/tmp/pma.tar.gz', '/tmp/')
                Log.debug(self, 'Extracting file /tmp/pma.tar.gz to '
                          'loaction /tmp/')
                if not os.path.exists('/var/www/22222/htdocs/db'):
                    Log.debug(self, "Creating new  directory "
                              "/var/www/22222/htdocs/db")
                    os.makedirs('/var/www/22222/htdocs/db')
                shutil.move('/tmp/phpmyadmin-STABLE/',
                            '/var/www/22222/htdocs/db/pma/')
                Log.debug(self, 'Setting Privileges of www-data:www-data to  '
                          '/var/www/22222/htdocs/db/pma file ')
                # EEShellExec.cmd_exec(self, 'chown -R www-data:www-data '
                #                     '/var/www/22222/htdocs/db/pma')
                EEFileUtils.chown(self, '/var/www/22222',
                                  EEVariables.ee_php_user,
                                  EEVariables.ee_php_user,
                                  recursive=True)
            if any('/tmp/memcache.tar.gz' == x[1]
                    for x in packages):
                Log.debug(self, "Extracting memcache.tar.gz to location"
                          " /var/www/22222/htdocs/cache/memcache ")
                EEExtract.extract(self, '/tmp/memcache.tar.gz',
                                  '/var/www/22222/htdocs/cache/memcache')
                Log.debug(self, "Setting Privileges to "
                          "/var/www/22222/htdocs/cache/memcache file")
                # EEShellExec.cmd_exec(self, 'chown -R www-data:www-data '
                #                     '/var/www/22222/htdocs/cache/memcache')
                EEFileUtils.chown(self, '/var/www/22222',
                                  EEVariables.ee_php_user,
                                  EEVariables.ee_php_user,
                                  recursive=True)

            if any('/tmp/webgrind.tar.gz' == x[1]
                    for x in packages):
                Log.debug(self, "Extracting file webgrind.tar.gz to "
                          "location /tmp/ ")
                EEExtract.extract(self, '/tmp/webgrind.tar.gz', '/tmp/')
                if not os.path.exists('/var/www/22222/htdocs/php'):
                    Log.debug(self, "Creating directroy "
                              "/var/www/22222/htdocs/php")
                    os.makedirs('/var/www/22222/htdocs/php')
                shutil.move('/tmp/webgrind-master/',
                            '/var/www/22222/htdocs/php/webgrind')
                Log.debug(self, "Setting Privileges of www-data:www-data to "
                          "/var/www/22222/htdocs/php/webgrind/ file ")
                # EEShellExec.cmd_exec(self, 'chown -R www-data:www-data '
                #                     '/var/www/22222/htdocs/php/webgrind/')
                EEFileUtils.chown(self, '/var/www/22222',
                                  EEVariables.ee_php_user,
                                  EEVariables.ee_php_user,
                                  recursive=True)

            if any('/tmp/anemometer.tar.gz' == x[1]
                    for x in packages):
                Log.debug(self, "Extracting file anemometer.tar.gz to "
                          "location /tmp/ ")
                EEExtract.extract(self, '/tmp/anemometer.tar.gz', '/tmp/')
                if not os.path.exists('/var/www/22222/htdocs/db/'):
                    Log.debug(self, "Creating directory")
                    os.makedirs('/var/www/22222/htdocs/db/')
                shutil.move('/tmp/Anemometer-master',
                            '/var/www/22222/htdocs/db/anemometer')
                chars = ''.join(random.sample(string.ascii_letters, 8))
                EEShellExec.cmd_exec(self, 'mysql < /var/www/22222/htdocs/db'
                                     '/anemometer/install.sql')
                EEMysql.execute(self, 'grant select on *.* to \'anemometer\''
                                '@\'{0}\''.format(self.app.config.get('mysql',
                                                  'grant-host')))
                EEMysql.execute(self, 'grant all on slow_query_log.* to'
                                '\'anemometer\'@\'{0}\' IDENTIFIED'
                                ' BY \'{1}\''.format(self.app.config.get(
                                                     'mysql', 'grant-host'),
                                                     chars))

                # Custom Anemometer configuration
                Log.debug(self, "configration Anemometer")
                data = dict(host=EEVariables.ee_mysql_host, port='3306',
                            user='anemometer', password=chars)
                ee_anemometer = open('/var/www/22222/htdocs/db/anemometer'
                                     '/conf/config.inc.php', 'w')
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
                if not os.path.exists('/var/www/22222/htdocs/'):
                    Log.debug(self, "Creating directory "
                              "/var/www/22222/htdocs/")
                    os.makedirs('/var/www/22222/htdocs/')
                shutil.move('/tmp/ViMbAdmin-{0}/'
                            .format(EEVariables.ee_vimbadmin),
                            '/var/www/22222/htdocs/vimbadmin/')

                # Donwload composer and install ViMbAdmin
                Log.debug(self, "Downloading composer "
                          "https://getcomposer.org/installer | php ")
                EEShellExec.cmd_exec(self, "cd /var/www/22222/htdocs"
                                     "/vimbadmin; curl"
                                     " -sS https://getcomposer.org/installer |"
                                     " php")
                Log.debug(self, "installation of composer")
                EEShellExec.cmd_exec(self, "cd /var/www/22222/htdocs"
                                     "/vimbadmin && "
                                     "php composer.phar install --prefer-dist"
                                     " --no-dev && rm -f /var/www/22222/htdocs"
                                     "/vimbadmin/composer.phar")

                # Configure vimbadmin database
                vm_passwd = ''.join(random.sample(string.ascii_letters, 8))
                Log.debug(self, "Creating vimbadmin database if not exist")
                EEMysql.execute(self, "create database if not exists"
                                      " vimbadmin")
                Log.debug(self, "Granting all privileges on vimbadmin ")
                EEMysql.execute(self, "grant all privileges on vimbadmin.* to"
                                " vimbadmin@{0} IDENTIFIED BY"
                                " '{1}'".format(self.app.config.get('mysql',
                                                'grant-host'), vm_passwd))

                # Configure ViMbAdmin settings
                config = configparser.ConfigParser(strict=False)
                Log.debug(self, "configuring ViMbAdmin ")
                config.read('/var/www/22222/htdocs/vimbadmin/application/'
                            'configs/application.ini.dist')
                config['user']['defaults.mailbox.uid'] = '5000'
                config['user']['defaults.mailbox.gid'] = '5000'
                config['user']['defaults.mailbox.maildir'] = ("maildir:/var/v"
                                                              + "mail/%%d/%%u")
                config['user']['defaults.mailbox.homedir'] = ("/srv/vmail/"
                                                              + "%%d/%%u")
                config['user']['resources.doctrine2.connection.'
                               'options.driver'] = 'mysqli'
                config['user']['resources.doctrine2.connection.'
                               'options.password'] = vm_passwd
                config['user']['resources.doctrine2.connection.'
                               'options.host'] = EEVariables.ee_mysql_host
                config['user']['defaults.mailbox.password_scheme'] = 'md5'
                config['user']['securitysalt'] = (''.join(random.sample
                                                  (string.ascii_letters
                                                   + string.ascii_letters,
                                                   64)))
                config['user']['resources.auth.'
                               'oss.rememberme.salt'] = (''.join(random.sample
                                                         (string.ascii_letters
                                                          + string.
                                                             ascii_letters,
                                                          64)))
                vm_salt = (''.join(random.sample(string.ascii_letters +
                                                 string.ascii_letters, 64)))
                config['user']['defaults.mailbox.'
                               'password_salt'] = vm_salt
                Log.debug(self, "Writting configuration to file "
                          "/var/www/22222/htdocs/vimbadmin"
                          "/application/configs/application.ini ")
                with open('/var/www/22222/htdocs/vimbadmin/application'
                          '/configs/application.ini', 'w') as configfile:
                    config.write(configfile)

                shutil.copyfile("/var/www/22222/htdocs/vimbadmin/public/"
                                ".htaccess.dist",
                                "/var/www/22222/htdocs/vimbadmin/public/"
                                ".htaccess")
                Log.debug(self, "Executing command "
                          "/var/www/22222/htdocs/vimbadmin/bin"
                          "/doctrine2-cli.php orm:schema-tool:"
                          "create")
                EEShellExec.cmd_exec(self, "/var/www/22222/htdocs/vimbadmin"
                                     "/bin/doctrine2-cli.php orm:schema-tool:"
                                     "create")

                EEFileUtils.chown(self, '/var/www/22222',
                                  EEVariables.ee_php_user,
                                  EEVariables.ee_php_user,
                                  recursive=True)

                # Copy Dovecot and Postfix templates which are depednet on
                # Vimbadmin

                if not os.path.exists('/etc/postfix/mysql/'):
                    Log.debug(self, "Creating directory "
                              "/etc/postfix/mysql/")
                    os.makedirs('/etc/postfix/mysql/')
                data = dict(password=vm_passwd, host=EEVariables.ee_mysql)
                vm_config = open('/etc/postfix/mysql/virtual_alias_maps.cf',
                                 'w')
                self.app.render((data), 'virtual_alias_maps.mustache',
                                out=vm_config)
                vm_config.close()

                Log.debug(self, "Writting configuration to  "
                          "/etc/postfix/mysql"
                          "/virtual_domains_maps.cf file")
                vm_config = open('/etc/postfix/mysql/virtual_domains_maps.cf',
                                 'w')
                self.app.render((data), 'virtual_domains_maps.mustache',
                                out=vm_config)
                vm_config.close()

                Log.debug(self, "Writting configuration to "
                          "/etc/postfix/mysql"
                          "/virtual_mailbox_maps.cf file")
                vm_config = open('/etc/postfix/mysql/virtual_mailbox_maps.cf',
                                 'w')
                self.app.render((data), 'virtual_mailbox_maps.mustache',
                                out=vm_config)
                vm_config.close()

                Log.debug(self, "Writting configration"
                                " to /etc/dovecot/dovecot-sql.conf.ext file ")
                vm_config = open('/etc/dovecot/dovecot-sql.conf.ext',
                                 'w')
                self.app.render((data), 'dovecot-sql-conf.mustache',
                                out=vm_config)
                vm_config.close()

                # If Amavis is going to be installed then configure Vimabadmin
                # Amvis settings
                if set(EEVariables.ee_mailscanner).issubset(set(apt_packages)):
                    vm_config = open('/etc/amavis/conf.d/50-user',
                                     'w')
                    self.app.render((data), '50-user.mustache',
                                    out=vm_config)
                    vm_config.close()
                EEService.reload_service(self, 'dovecot')
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
                if not os.path.exists('/var/www/roundcubemail'):
                    Log.debug(self, "Creating new directory "
                              " /var/www/roundcubemail/")
                    os.makedirs('/var/www/roundcubemail/')
                shutil.move('/tmp/roundcubemail-1.0.4/',
                            '/var/www/roundcubemail/htdocs')

                # Configure roundcube database
                rc_passwd = ''.join(random.sample(string.ascii_letters, 8))
                Log.debug(self, "Creating Database roundcubemail")
                EEMysql.execute(self, "create database if not exists "
                                " roundcubemail")
                Log.debug(self, "Grant all privileges on roundcubemail")
                EEMysql.execute(self, "grant all privileges"
                                " on roundcubemail.* to "
                                " roundcube@{0} IDENTIFIED BY "
                                "'{1}'".format(self.app.config.get(
                                               'mysql', 'grant-host'),
                                               rc_passwd))
                EEShellExec.cmd_exec(self, "mysql roundcubemail < /var/www/"
                                     "roundcubemail/htdocs/SQL/mysql"
                                     ".initial.sql")

                shutil.copyfile("/var/www/roundcubemail/htdocs/config/"
                                "config.inc.php.sample",
                                "/var/www/roundcubemail/htdocs/config/"
                                "config.inc.php")
                EEShellExec.cmd_exec(self, "sed -i \"s\'mysql://roundcube:"
                                     "pass@localhost/roundcubemail\'mysql://"
                                     "roundcube:{0}@{1}/"
                                     "roundcubemail\'\" /var/www/roundcubemail"
                                     "/htdocs/config/config."
                                     "inc.php"
                                     .format(rc_passwd,
                                             EEVariables.ee_mysql_host))

                # Sieve plugin configuration in roundcube
                EEShellExec.cmd_exec(self, "bash -c \"sed -i \\\"s:\$config\["
                                     "\'plugins\'\] "
                                     "= array(:\$config\['plugins'\] =  "
                                     "array(\n\'sieverules\',:\\\" /var/www"
                                     "/roundcubemail/htdocs/config"
                                     "/config.inc.php\"")
                EEShellExec.cmd_exec(self, "echo \"\$config['sieverules_port']"
                                     "=4190;\" >> /var/www/roundcubemail"
                                     "/htdocs/config/config.inc.php")

                data = dict(site_name='webmail', www_domain='webmail',
                            static=False,
                            basic=True, wp=False, w3tc=False, wpfc=False,
                            wpsc=False, multisite=False, wpsubdir=False,
                            webroot='/var/www', ee_db_name='',
                            ee_db_user='', ee_db_pass='', ee_db_host='',
                            rc=True)

                Log.debug(self, 'Writting the nginx configuration for '
                          'RoundCubemail')
                ee_rc = open('/etc/nginx/sites-available/webmail.conf', 'w')
                self.app.render((data), 'virtualconf.mustache',
                                out=ee_rc)
                ee_rc.close()

                # Create Symbolic link for webmail.conf
                EEFileUtils.create_symlink(self, ['/etc/nginx/sites-available'
                                                  '/webmail.conf',
                                                  '/etc/nginx/sites-enabled/'
                                                  'webmail.conf'])
                # Create log folder and softlinks
                if not os.path.exists('/var/www/roundcubemail/logs'):
                    os.makedirs('/var/www/roundcubemail/logs')

                EEFileUtils.create_symlink(self, ['/var/log/nginx/'
                                                  'webmail.access.log',
                                                  '/var/www/roundcubemail/'
                                                  'logs/access.log'])

                EEFileUtils.create_symlink(self, ['/var/log/nginx/'
                                                  'webmail.error.log',
                                                  '/var/www/roundcubemail/'
                                                  'logs/error.log'])
                # Remove roundcube installer
                EEService.reload_service(self, 'nginx')
                EEFileUtils.remove(self, ["/var/www/roundcubemail"
                                   "/htdocs/installer"])
                EEFileUtils.chown(self, '/var/www/roundcubemail',
                                  EEVariables.ee_php_user,
                                  EEVariables.ee_php_user,
                                  recursive=True)

    @expose(help="Install packages")
    def install(self, packages=[], apt_packages=[], disp_msg=True):
        self.msg = []
        try:
            # Default action for stack installation
            if ((not self.app.pargs.web) and (not self.app.pargs.admin) and
               (not self.app.pargs.mail) and (not self.app.pargs.nginx) and
               (not self.app.pargs.php) and (not self.app.pargs.mysql) and
               (not self.app.pargs.postfix) and (not self.app.pargs.wpcli) and
               (not self.app.pargs.phpmyadmin) and
               (not self.app.pargs.adminer) and (not self.app.pargs.utils)):
                self.app.pargs.web = True

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
                        apt_packages = (apt_packages +
                                        EEVariables.ee_mailscanner)
                else:
                    Log.info(self, "Mail server is already installed")

            if self.app.pargs.nginx:
                Log.debug(self, "Setting apt_packages variable for Nginx")
                if not EEAptGet.is_installed(self, 'nginx-common'):
                    apt_packages = apt_packages + EEVariables.ee_nginx
                else:
                    Log.debug(self, "Nginx already installed")
            if self.app.pargs.php:
                Log.debug(self, "Setting apt_packages variable for PHP")
                if not EEAptGet.is_installed(self, 'php5-fpm'):
                    apt_packages = apt_packages + EEVariables.ee_php
                else:
                    Log.debug(self, "PHP already installed")
            if self.app.pargs.mysql:
                Log.debug(self, "Setting apt_packages variable for MySQL")
                if not EEShellExec.cmd_exec(self, "mysqladmin ping"):
                    apt_packages = apt_packages + EEVariables.ee_mysql
                else:
                    Log.debug(self, "MySQL connection is already alive")
            if self.app.pargs.postfix:
                Log.debug(self, "Setting apt_packages variable for Postfix")
                if not EEAptGet.is_installed(self, 'postfix'):
                    apt_packages = apt_packages + EEVariables.ee_postfix
                else:
                    Log.debug(self, "Postfix is already installed")
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
                                        "/var/www/22222/"
                                        "htdocs/db/adminer/index.php",
                                        "Adminer"]]

            if self.app.pargs.utils:
                Log.debug(self, "Setting packages variable for utils")
                packages = packages + [["http://phpmemcacheadmin.googlecode"
                                        ".com/files/phpMemcachedAdmin-1.2.2"
                                        "-r262.tar.gz", '/tmp/memcache.tar.gz',
                                        'phpMemcachedAdmin'],
                                       ["https://raw.githubusercontent.com"
                                        "/rtCamp/eeadmin/master/cache/nginx/"
                                        "clean.php",
                                        "/var/www/22222/htdocs/cache/"
                                        "nginx/clean.php", "clean.php"],
                                       ["https://raw.github.com/rlerdorf/"
                                        "opcache-status/master/opcache.php",
                                        "/var/www/22222/htdocs/cache/"
                                        "opcache/opcache.php", "opcache.php"],
                                       ["https://raw.github.com/amnuts/"
                                        "opcache-gui/master/index.php",
                                        "/var/www/22222/htdocs/"
                                        "cache/opcache/opgui.php",
                                        "Opgui"],
                                       ["https://gist.github.com/ck-on/4959032"
                                        "/raw/0b871b345fd6cfcd6d2be030c1f33d1"
                                        "ad6a475cb/ocp.php",
                                        "/var/www/22222/htdocs/cache/"
                                        "opcache/ocp.php", "OCP.php"],
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
                Log.debug(self, "Updating apt-cache")
                EEAptGet.update(self)
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
        apt_packages = []
        packages = []

        # Default action for stack remove
        if ((not self.app.pargs.web) and (not self.app.pargs.admin) and
           (not self.app.pargs.mail) and (not self.app.pargs.nginx) and
           (not self.app.pargs.php) and (not self.app.pargs.mysql) and
           (not self.app.pargs.postfix) and (not self.app.pargs.wpcli) and
           (not self.app.pargs.phpmyadmin) and
           (not self.app.pargs.adminer) and (not self.app.pargs.utils)):
            self.app.pargs.web = True

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
            packages = packages + ["/var/www/22222/htdocs/vimbadmin",
                                   "/var/www/roundcubemail"]
            if EEShellExec.cmd_exec(self, "mysqladmin ping"):
                EEMysql.execute(self, "drop database IF EXISTS vimbadmin")
                EEMysql.execute(self, "drop database IF EXISTS roundcubemail")

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
            packages = packages + ['/var/www/22222/htdocs/db/pma']
        if self.app.pargs.adminer:
            Log.debug(self, "Removing package variable of Adminer ")
            packages = packages + ['/var/www/22222/htdocs/db/Adminer']
        if self.app.pargs.utils:
            Log.debug(self, "Removing package variable of utils ")
            packages = packages + ['/var/www/22222/htdocs/php/webgrind/',
                                   '/var/www/22222/htdocs/cache/opcache',
                                   '/var/www/22222/htdocs/cache/Nginx/'
                                   'clean.php',
                                   '/var/www/22222/htdocs/cache/Memcache',
                                   '/usr/bin/pt-query-advisor',
                                   '/var/www/22222/htdocs/db/Anemometer']

        if len(apt_packages):
            Log.debug(self, "Removing apt_packages")
            EEAptGet.remove(self, apt_packages)
        if len(packages):
            EEFileUtils.remove(self, packages)
        Log.info(self, "Successfully removed packages")

    @expose(help="Purge packages")
    def purge(self):
        apt_packages = []
        packages = []

        # Default action for stack purge
        if ((not self.app.pargs.web) and (not self.app.pargs.admin) and
           (not self.app.pargs.mail) and (not self.app.pargs.nginx) and
           (not self.app.pargs.php) and (not self.app.pargs.mysql) and
           (not self.app.pargs.postfix) and (not self.app.pargs.wpcli) and
           (not self.app.pargs.phpmyadmin) and
           (not self.app.pargs.adminer) and (not self.app.pargs.utils)):
            self.app.pargs.web = True

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
            packages = packages + ["/var/www/22222/htdocs/vimbadmin",
                                   "/var/www/roundcubemail"]
            if EEShellExec.cmd_exec(self, "mysqladmin ping"):
                EEMysql.execute(self, "drop database IF EXISTS vimbadmin")
                EEMysql.execute(self, "drop database IF EXISTS roundcubemail")

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
            packages = packages + ['/var/www/22222/htdocs/db/pma']
            Log.debug(self, "Purge package variable phpMyAdmin")
        if self.app.pargs.adminer:
            Log.debug(self, "Purge  package variable Adminer")
            packages = packages + ['/var/www/22222/htdocs/db/adminer']
        if self.app.pargs.utils:
            Log.debug(self, "Purge package variable utils")
            packages = packages + ['/var/www/22222/htdocs/php/webgrind/',
                                   '/var/www/22222/htdocs/cache/opcache',
                                   '/var/www/22222/htdocs/cache/nginx/'
                                   'clean.php',
                                   '/var/www/22222/htdocs/cache/memcache',
                                   '/usr/bin/pt-query-advisor',
                                   '/var/www/22222/htdocs/db/anemometer'
                                   ]

        if len(apt_packages):
            EEAptGet.remove(self, apt_packages, purge=True)
        if len(packages):
            EEFileUtils.remove(self, packages)
        Log.info(self, "Successfully purged packages")


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EEStackController)
    handler.register(EEStackStatusController)

    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', ee_stack_hook)
