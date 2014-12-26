"""Example Plugin for EasyEngine."""

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
from pynginxconfig import NginxConfig
import random
import string
import configparser
import time
import shutil
import os
import pwd
import grp
from ee.cli.plugins.stack_services import EEStackStatusController


def ee_stack_hook(app):
    # do something with the ``app`` object here.
    pass


class EEStackController(CementBaseController):
    class Meta:
        label = 'stack'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = 'stack command manages stack operations'
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
        print("Inside EEStackController.default().")

    @expose(hide=True)
    def pre_pref(self, apt_packages):
        if set(EEVariables.ee_postfix).issubset(set(apt_packages)):
            print("Pre-seeding postfix variables ... ")
            EEShellExec.cmd_exec("echo \"postfix postfix/main_mailer_type "
                                 "string 'Internet Site'\" | "
                                 "debconf-set-selections")
            EEShellExec.cmd_exec("echo \"postfix postfix/mailname string "
                                 "$(hostname -f)\" | debconf-set-selections")
        if set(EEVariables.ee_mysql).issubset(set(apt_packages)):
            print("Adding repository for MySQL ... ")
            EERepo.add(repo_url=EEVariables.ee_mysql_repo)
            self.app.log.debug('Adding key of MySQL.')
            EERepo.add_key('1C4CBDCDCD2EFD2A')
            chars = ''.join(random.sample(string.ascii_letters, 8))
            print("Pre-seeding MySQL variables ... ")
            EEShellExec.cmd_exec("echo \"percona-server-server-5.6 "
                                 "percona-server-server/root_password "
                                 "password {chars}\" | "
                                 "debconf-set-selections".format(chars=chars))
            EEShellExec.cmd_exec("echo \"percona-server-server-5.6 "
                                 "percona-server-server/root_password_again "
                                 "password {chars}\" | "
                                 "debconf-set-selections".format(chars=chars))
            mysql_config = """
            [mysqld]
            user = root
            password = {chars}
            """.format(chars=chars)
            config = configparser.ConfigParser()
            config.read_string(mysql_config)
            self.app.log.debug('writting configartion into MySQL file.')
            with open(os.path.expanduser("~")+'/.my.cnf', 'w') as configfile:
                config.write(configfile)

        if set(EEVariables.ee_nginx).issubset(set(apt_packages)):
            print("Adding repository for Nginx ... ")
            if EEVariables.ee_platform_distro == 'Debian':
                self.app.log.debug('Adding Dotdeb/nginx GPG key')
                EERepo.add(repo_url=EEVariables.ee_nginx_repo)
            else:
                self.app.log.debug('Adding ppa of Nginx')
                EERepo.add(ppa=EEVariables.ee_nginx_repo)

        if set(EEVariables.ee_php).issubset(set(apt_packages)):
            print("Adding repository for PHP ... ")
            if EEVariables.ee_platform_distro == 'Debian':
                self.app.log.debug('Adding repo_url of php for Debian')
                EERepo.add(repo_url=EEVariables.ee_php_repo)
                self.app.log.debug('Adding  Dotdeb/php GPG key')
                EERepo.add_key('89DF5277')
            else:
                self.app.log.debug('Adding ppa for PHP')
                EERepo.add(ppa=EEVariables.ee_php_repo)

        if set(EEVariables.ee_mail).issubset(set(apt_packages)):
            if EEVariables.ee_platform_codename == 'squeeze':
                print("Adding repository for dovecot ... ")
                EERepo.add(repo_url=EEVariables.ee_dovecot_repo)
            self.app.log.debug('Executing the command debconf-set-selections.')
            EEShellExec.cmd_exec("echo \"dovecot-core dovecot-core/"
                                 "create-ssl-cert boolean yes\" "
                                 "| debconf-set-selections")
            EEShellExec.cmd_exec("echo \"dovecot-core dovecot-core/ssl-cert-"
                                 "name string $(hostname -f)\""
                                 " | debconf-set-selections")

    @expose(hide=True)
    def post_pref(self, apt_packages, packages):
        if len(apt_packages):
            if set(EEVariables.ee_postfix).issubset(set(apt_packages)):
                pass
            if set(EEVariables.ee_nginx).issubset(set(apt_packages)):
                # Nginx core configuration change using configparser
                nc = NginxConfig()
                print('in nginx')
                self.app.log.debug('Loading file /etc/nginx/nginx.conf ')
                nc.loadf('/etc/nginx/nginx.conf')
                nc.set('worker_processes', 'auto')
                nc.append(('worker_rlimit_nofile', '100000'), position=2)
                nc.remove(('events', ''))
                nc.append({'name': 'events', 'param': '', 'value':
                           [('worker_connections', '4096'),
                            ('multi_accept', 'on')]}, position=4)
                nc.set([('http',), 'keepalive_timeout'], '30')
                self.app.log.debug("Writting nginx configration to "
                                   "file /etc/nginx/nginx.conf ")
                nc.savef('/etc/nginx/nginx.conf')

                # Custom Nginx configuration by EasyEngine
                data = dict(version='EasyEngine 3.0.1')
                self.app.log.debug('writting the nginx configration to file'
                                   '/etc/nginx/conf.d/ee-nginx.conf ')
                ee_nginx = open('/etc/nginx/conf.d/ee-nginx.conf', 'w')
                self.app.render((data), 'nginx-core.mustache', out=ee_nginx)
                ee_nginx.close()

            if set(EEVariables.ee_php).issubset(set(apt_packages)):
                # Parse etc/php5/fpm/php.ini
                config = configparser.ConfigParser()
                self.app.log.debug("configring php file /etc/php5/fpm/php.ini")
                config.read('/etc/php5/fpm/php.ini')
                config['PHP']['expose_php'] = 'Off'
                config['PHP']['post_max_size'] = '100M'
                config['PHP']['upload_max_filesize'] = '100M'
                config['PHP']['max_execution_time'] = '300'
                config['PHP']['date.timezone'] = time.tzname[time.daylight]
                with open('/etc/php5/fpm/php.ini', 'w') as configfile:
                    self.app.log.debug("writting configration of php in to"
                                       "file /etc/php5/fpm/php.ini")
                    config.write(configfile)

                # Prase /etc/php5/fpm/php-fpm.conf
                config = configparser.ConfigParser()
                config.read('/etc/php5/fpm/php-fpm.conf')
                config['global']['error_log'] = '/var/log/php5/fpm.log'
                with open('/etc/php5/fpm/php-fpm.conf', 'w') as configfile:
                    self.app.log.debug("writting php5 configartion into "
                                       " /etc/php5/fpm/php-fpm.conf")
                    config.write(configfile)

                # Parse /etc/php5/fpm/pool.d/www.conf
                config = configparser.ConfigParser()
                config.read('/etc/php5/fpm/pool.d/www.conf')
                config['www']['ping.path'] = '/ping'
                config['www']['pm.status_path'] = '/status'
                config['www']['pm.max_requests'] = '500'
                config['www']['pm.max_children'] = ''
                config['www']['pm.start_servers'] = '20'
                config['www']['pm.min_spare_servers'] = '10'
                config['www']['pm.max_spare_servers'] = '30'
                config['www']['request_terminate_timeout'] = '300'
                config['www']['pm'] = 'ondemand'
                config['www']['listen'] = '127.0.0.1:9000'
                with open('/etc/php5/fpm/pool.d/www.conf', 'w') as configfile:
                    self.app.log.debug("writting PHP5 configartion into "
                                       " /etc/php5/fpm/pool.d/www.conf")
                    config.write(configfile)

            if set(EEVariables.ee_mysql).issubset(set(apt_packages)):
                config = configparser.ConfigParser()
                config.read('/etc/mysql/my.cnf')
                config['mysqld']['wait_timeout'] = 30
                config['mysqld']['interactive_timeout'] = 60
                config['mysqld']['performance_schema'] = 0
                with open('/etc/mysql/my.cnf', 'w') as configfile:
                    config.write(configfile)

            if set(EEVariables.ee_mail).issubset(set(apt_packages)):
                self.app.log.debug("Executing mail commands")
                EEShellExec.cmd_exec("adduser --uid 5000 --home /var/vmail"
                                     "--disabled-password --gecos '' vmail")
                EEShellExec.cmd_exec("openssl req -new -x509 -days 3650 -nodes"
                                     " -subj /commonName={HOSTNAME}/emailAddre"
                                     "ss={EMAIL} -out /etc/ssl/certs/dovecot."
                                     "pem -keyout /etc/ssl/private/dovecot.pem"
                                     .format(HOSTNAME=EEVariables.ee_fqdn,
                                             EMAIL=EEVariables.ee_email))
                self.app.log.debug("Adding Privillages to file "
                                   "/etc/ssl/private/dovecot.pem ")
                EEShellExec.cmd_exec("chmod 0600 /etc/ssl/private/dovecot.pem")

                # Custom Dovecot configuration by EasyEngine
                data = dict()
                self.app.log.debug("Writting configration into file"
                                   "/etc/dovecot/conf.d/99-ee.conf ")
                ee_dovecot = open('/etc/dovecot/conf.d/99-ee.conf', 'w')
                self.app.render((data), 'dovecot.mustache', out=ee_dovecot)
                ee_dovecot.close()

                # Custom Postfix configuration needed with Dovecot
                # Changes in master.cf
                # TODO: Find alternative for sed in Python
                EEShellExec.cmd_exec("sed -i \'s/#submission/submission/\'"
                                     " /etc/postfix/master.cf")
                EEShellExec.cmd_exec("sed -i \'s/#smtps/smtps/\'"
                                     " /etc/postfix/master.cf")

                EEShellExec.cmd_exec("postconf -e \"smtpd_sasl_type = "
                                     "dovecot\"")
                EEShellExec.cmd_exec("postconf -e \"smtpd_sasl_path = "
                                     "private/auth\"")
                EEShellExec.cmd_exec("postconf -e \"smtpd_sasl_auth_enable = "
                                     "yes\"")
                EEShellExec.cmd_exec("postconf -e \"smtpd_relay_restrictions ="
                                     " permit_sasl_authenticated, "
                                     "permit_mynetworks, "
                                     "reject_unauth_destination\"")
                EEShellExec.cmd_exec("postconf -e \"smtpd_tls_mandatory_"
                                     "protocols = !SSLv2,!SSLv3\"")
                EEShellExec.cmd_exec("postconf -e \"smtp_tls_mandatory_"
                                     "protocols = !SSLv2,!SSLv3\"")
                EEShellExec.cmd_exec("postconf -e \"smtpd_tls_protocols "
                                     "= !SSLv2,!SSLv3\"")
                EEShellExec.cmd_exec("postconf -e \"smtp_tls_protocols "
                                     "= !SSLv2,!SSLv3\"")
                EEShellExec.cmd_exec("postconf -e \"mydestination "
                                     "= localhost\"")
                EEShellExec.cmd_exec("postconf -e \"virtual_transport "
                                     "= lmtp:unix:private/dovecot-lmtp\"")
                EEShellExec.cmd_exec("postconf -e \"virtual_uid_maps "
                                     "= static:5000\"")
                EEShellExec.cmd_exec("postconf -e \"virtual_gid_maps "
                                     "= static:5000\"")
                EEShellExec.cmd_exec("postconf -e \"virtual_mailbox_domains = "
                                     "mysql:/etc/postfix/mysql/virtual_"
                                     "domains_maps.cf\"")
                EEShellExec.cmd_exec("postconf -e \"virtual_mailbox_maps = "
                                     "mysql:/etc/postfix/mysql/virtual_"
                                     "mailbox_maps.cf\"")
                EEShellExec.cmd_exec("postconf -e \"virtual_alias_maps = "
                                     "mysql:/etc/postfix/mysql/virtual_"
                                     "alias_maps.cf\"")
                EEShellExec.cmd_exec("openssl req -new -x509 -days 3650 -nodes"
                                     " -subj /commonName={HOSTNAME}/emailAddre"
                                     "ss={EMAIL} -out /etc/ssl/certs/postfix."
                                     "pem -keyout /etc/ssl/private/postfix.pem"
                                     .format(HOSTNAME=EEVariables.ee_fqdn,
                                             EMAIL=EEVariables.ee_email))
                EEShellExec.cmd_exec("chmod 0600 /etc/ssl/private/postfix.pem")
                EEShellExec.cmd_exec("postconf -e \"smtpd_tls_cert_file = "
                                     "/etc/ssl/certs/postfix.pem\"")
                EEShellExec.cmd_exec("postconf -e \"smtpd_tls_key_file = "
                                     "/etc/ssl/private/postfix.pem\"")

                # Sieve configuration
                if not os.path.exists('/var/lib/dovecot/sieve/'):
                    self.app.log.debug('Creating directory'
                                       '/var/lib/dovecot/sieve/ ')
                    os.makedirs('/var/lib/dovecot/sieve/')

                # Custom sieve configuration by EasyEngine
                data = dict()
                self.app.log.debug("Writting configaration of EasyEngine into"
                                   "file /var/lib/dovecot/sieve/default.sieve")
                ee_sieve = open('/var/lib/dovecot/sieve/default.sieve', 'w')
                self.app.render((data), 'default-sieve.mustache',
                                out=ee_sieve)
                ee_sieve.close()

                # Compile sieve rules
                self.app.log.debug("Privillages to dovecot ")
                EEShellExec.cmd_exec("chown -R vmail:vmail /var/lib/dovecot")
                EEShellExec.cmd_exec("sievec /var/lib/dovecot/sieve/"
                                     "default.sieve")

        if len(packages):
            if any('/usr/bin/wp' == x[1] for x in packages):
                EEShellExec.cmd_exec("chmod +x /usr/bin/wp")
            if any('/tmp/pma.tar.gz' == x[1]
                    for x in packages):
                EEExtract.extract('/tmp/pma.tar.gz', '/tmp/')
                self.app.log.debug('Extracting file /tmp/pma.tar.gz to '
                                   'loaction /tmp/')
                if not os.path.exists('/var/www/22222/htdocs/db'):
                    self.app.log.debug("Creating new  directory "
                                       "/var/www/22222/htdocs/db")
                    os.makedirs('/var/www/22222/htdocs/db')
                shutil.move('/tmp/phpmyadmin-STABLE/',
                            '/var/www/22222/htdocs/db/pma/')
                self.app.log.debug('Privillages to www-data:www-data '
                                   '/var/www/22222/htdocs/db/pma ')
                EEShellExec.cmd_exec('chown -R www-data:www-data '
                                     '/var/www/22222/htdocs/db/pma')
            if any('/tmp/memcache.tar.gz' == x[1]
                    for x in packages):
                self.app.log.debug("Extracting memcache.tar.gz to location"
                                   " /var/www/22222/htdocs/cache/memcache ")
                EEExtract.extract('/tmp/memcache.tar.gz',
                                  '/var/www/22222/htdocs/cache/memcache')
                self.app.log.debug("Privillages to"
                                   " /var/www/22222/htdocs/cache/memcache")
                EEShellExec.cmd_exec('chown -R www-data:www-data '
                                     '/var/www/22222/htdocs/cache/memcache')

            if any('/tmp/webgrind.tar.gz' == x[1]
                    for x in packages):
                self.app.log.debug("Extracting file webgrind.tar.gz to "
                                   "location /tmp/ ")
                EEExtract.extract('/tmp/webgrind.tar.gz', '/tmp/')
                if not os.path.exists('/var/www/22222/htdocs/php'):
                    self.app.log.debug("Creating directroy "
                                       "/var/www/22222/htdocs/php")
                    os.makedirs('/var/www/22222/htdocs/php')
                shutil.move('/tmp/webgrind-master/',
                            '/var/www/22222/htdocs/php/webgrind')
                self.app.log.debug("Privillages www-data:www-data "
                                   "/var/www/22222/htdocs/php/webgrind/ ")
                EEShellExec.cmd_exec('chown -R www-data:www-data '
                                     '/var/www/22222/htdocs/php/webgrind/')

            if any('/tmp/anemometer.tar.gz' == x[1]
                    for x in packages):
                self.app.log.debug("Extracting file anemometer.tar.gz to "
                                   "location /tmp/ ")
                EEExtract.extract('/tmp/anemometer.tar.gz', '/tmp/')
                if not os.path.exists('/var/www/22222/htdocs/db/'):
                    self.app.log.debug("Creating directory")
                    os.makedirs('/var/www/22222/htdocs/db/')
                shutil.move('/tmp/Anemometer-master',
                            '/var/www/22222/htdocs/db/anemometer')
                chars = ''.join(random.sample(string.ascii_letters, 8))
                EEShellExec.cmd_exec('mysql < /var/www/22222/htdocs/db'
                                     '/anemometer/install.sql')
                EEMysql.execute('grant select on *.* to \'anemometer\''
                                '@\'localhost\'')
                EEMysql.execute('grant all on slow_query_log.* to'
                                '\'anemometer\'@\'localhost\' IDENTIFIED'
                                ' BY \''+chars+'\'')

                # Custom Anemometer configuration
                self.app.log.debug("configration Anemometer")
                data = dict(host='localhost', port='3306', user='anemometer',
                            password=chars)
                ee_anemometer = open('/var/www/22222/htdocs/db/anemometer'
                                     '/conf/config.inc.php', 'w')
                self.app.render((data), 'anemometer.mustache',
                                out=ee_anemometer)
                ee_anemometer.close()

            if any('/usr/bin/pt-query-advisor' == x[1]
                    for x in packages):
                EEShellExec.cmd_exec("chmod +x /usr/bin/pt-query-advisor")

            if any('/tmp/vimbadmin.tar.gz' == x[1] for x in packages):
                # Extract ViMbAdmin
                self.app.log.debug("Extracting ViMbAdmin.tar.gz to "
                                   "location /tmp/")
                EEExtract.extract('/tmp/vimbadmin.tar.gz', '/tmp/')
                if not os.path.exists('/var/www/22222/htdocs/'):
                    self.app.log.debug("Creating directory "
                                       " /var/www/22222/htdocs/")
                    os.makedirs('/var/www/22222/htdocs/')
                shutil.move('/tmp/ViMbAdmin-3.0.10/',
                            '/var/www/22222/htdocs/vimbadmin/')

                # Donwload composer and install ViMbAdmin
                self.app.log.debug("Downloading composer "
                                   "https://getcomposer.org/installer | php ")
                EEShellExec.cmd_exec("cd /var/www/22222/htdocs/vimbadmin; curl"
                                     " -sS https://getcomposer.org/installer |"
                                     " php")
                self.app.log.debug("installation of composer")
                EEShellExec.cmd_exec("cd /var/www/22222/htdocs/vimbadmin && "
                                     "php composer.phar install --prefer-dist"
                                     " --no-dev && rm -f /var/www/22222/htdocs"
                                     "/vimbadmin/composer.phar")

                # Configure vimbadmin database
                vm_passwd = ''.join(random.sample(string.ascii_letters, 8))
                self.app.log.debug("Creating vimbadmin database if not exist")
                EEMysql.execute("create database if not exists vimbadmin")
                self.app.log.debug("Granting all privileges on vimbadmin ")
                EEMysql.execute("grant all privileges on vimbadmin.* to"
                                " vimbadmin@localhost IDENTIFIED BY"
                                " '{password}'".format(password=vm_passwd))

                # Configure ViMbAdmin settings
                config = configparser.ConfigParser(strict=False)
                self.app.log.debug("configuring ViMbAdmin ")
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
                               'options.host'] = 'localhost'
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
                config['user']['defaults.mailbox.'
                               'password_salt'] = (''.join(random.sample
                                                   (string.ascii_letters
                                                    + string.ascii_letters,
                                                    64)))
                self.app.log.debug("Writting configration to file "
                                   "/var/www/22222/htdocs/vimbadmin"
                                   "/application/configs/application.ini ")
                with open('/var/www/22222/htdocs/vimbadmin/application'
                          '/configs/application.ini', 'w') as configfile:
                    config.write(configfile)

                shutil.copyfile("/var/www/22222/htdocs/vimbadmin/public/"
                                ".htaccess.dist",
                                "/var/www/22222/htdocs/vimbadmin/public/"
                                ".htaccess")
                self.app.log.debug("Executing command "
                                   "/var/www/22222/htdocs/vimbadmin/bin"
                                   "/doctrine2-cli.php orm:schema-tool:"
                                   "create" ")
                EEShellExec.cmd_exec("/var/www/22222/htdocs/vimbadmin/bin"
                                     "/doctrine2-cli.php orm:schema-tool:"
                                     "create")

                # Copy Dovecot and Postfix templates which are depednet on
                # Vimbadmin

                if not os.path.exists('/etc/postfix/mysql/'):
                    self.app.log.debug("Creating directory "
                                       "/etc/postfix/mysql/")
                    os.makedirs('/etc/postfix/mysql/')
                data = dict(password=vm_passwd)
                vm_config = open('/etc/postfix/mysql/virtual_alias_maps.cf',
                                 'w')
                self.app.render((data), 'virtual_alias_maps.mustache',
                                out=vm_config)
                vm_config.close()

                self.app.log.debug("Configration of file "
                                   "/etc/postfix/mysql"
                                   "/virtual_domains_maps.cf")
                vm_config = open('/etc/postfix/mysql/virtual_domains_maps.cf',
                                 'w')
                self.app.render((data), 'virtual_domains_maps.mustache',
                                out=vm_config)
                vm_config.close()

                self.app.log.debug("Configation of file "
                                   "/etc/postfix/mysql"
                                   "/virtual_mailbox_maps.cf ")
                vm_config = open('/etc/postfix/mysql/virtual_mailbox_maps.cf',
                                 'w')
                self.app.render((data), 'virtual_mailbox_maps.mustache',
                                out=vm_config)
                vm_config.close()

                self.app.log.debug("Configration of file ")
                vm_config = open('/etc/dovecot/dovecot-sql.conf.ext',
                                 'w')
                self.app.render((data), 'dovecot-sql-conf.mustache',
                                out=vm_config)
                vm_config.close()

            if any('/tmp/roundcube.tar.gz' == x[1] for x in packages):
                # Extract RoundCubemail
                self.app.log.debug("Extracting file /tmp/roundcube.tar.gz "
                                   "to location /tmp/ ")
                EEExtract.extract('/tmp/roundcube.tar.gz', '/tmp/')
                if not os.path.exists('/var/www/roundcubemail'):
                    self.app.log.debug("Creating new directory "
                                       " /var/www/roundcubemail/")
                    os.makedirs('/var/www/roundcubemail/')
                shutil.move('/tmp/roundcubemail-1.0.4/',
                            '/var/www/roundcubemail/htdocs')

                # Configure roundcube database
                rc_passwd = ''.join(random.sample(string.ascii_letters, 8))
                self.app.log.debug("Creating Database roundcubemail")
                EEMysql.execute("create database if not exists roundcubemail")
                self.app.log.debug("Grant all privileges on roundcubemail")
                EEMysql.execute("grant all privileges on roundcubemail.* to "
                                " roundcube@localhost IDENTIFIED BY "
                                "'{password}'".format(password=rc_passwd))
                EEShellExec.cmd_exec("mysql roundcubemail < /var/www/"
                                     "roundcubemail/htdocs/SQL/mysql"
                                     ".initial.sql")

                shutil.copyfile("/var/www/roundcubemail/htdocs/config/"
                                "config.inc.php.sample",
                                "/var/www/roundcubemail/htdocs/config/"
                                "config.inc.php")
                EEShellExec.cmd_exec("sed -i \"s\'mysql://roundcube:pass@"
                                     "localhost/roundcubemail\'mysql://"
                                     "roundcube:{password}@localhost/"
                                     "roundcubemail\'\" /var/www/roundcubemail"
                                     "/htdocs/config/config."
                                     "inc.php".format(password=rc_passwd))

                # Sieve plugin configuration in roundcube
                EEShellExec.cmd_exec("sed -i \"s:\$config\['plugins'\] = array"
                                     "(:\$config\['plugins'\] = array(\n "
                                     "'sieverules',:\" /var/www/roundcubemail"
                                     "/htdocs/config/config.inc.php")
                EEShellExec.cmd_exec("echo \"\$config['sieverules_port'] = "
                                     "4190;\" >> /var/www/roundcubemail/htdocs"
                                     "/config/config.inc.php")

    @expose()
    def install(self):
        pkg = EEAptGet()
        apt_packages = []
        packages = []

        if self.app.pargs.web:
            self.app.log.debug("Setting apt_packages variable for Nginx ,PHP"
                               " ,MySQL ")
            apt_packages = (apt_packages + EEVariables.ee_nginx +
                            EEVariables.ee_php + EEVariables.ee_mysql)

        if self.app.pargs.admin:
            pass
            # apt_packages = apt_packages + EEVariables.ee_nginx
        if self.app.pargs.mail:
            apt_packages = apt_packages + EEVariables.ee_mail
            self.app.log.debug("Setting apt_packages variable for mail")
            packages = packages + [["https://github.com/opensolutions/ViMbAdmi"
                                    "n/archive/3.0.10.tar.gz", "/tmp/vimbadmin"
                                    ".tar.gz"],
                                   ["https://github.com/roundcube/"
                                    "roundcubemail/releases/download/"
                                    "1.0.4/roundcubemail-1.0.4.tar.gz",
                                    "/tmp/roundcube.tar.gz"]
                                   ]

        if self.app.pargs.nginx:
            self.app.log.debug("Setting apt_packages variable for Nginx")
            apt_packages = apt_packages + EEVariables.ee_nginx
        if self.app.pargs.php:
            self.app.log.debug("Setting apt_packages variable for PHP")
            apt_packages = apt_packages + EEVariables.ee_php
        if self.app.pargs.mysql:
            self.app.log.debug("Setting apt_packages variable for MySQL")
            apt_packages = apt_packages + EEVariables.ee_mysql
        if self.app.pargs.postfix:
            self.app.log.debug("Setting apt_packages variable for PostFix")
            apt_packages = apt_packages + EEVariables.ee_postfix
        if self.app.pargs.wpcli:
            self.app.log.debug("Setting packages variable for WPCLI")
            packages = packages + [["https://github.com/wp-cli/wp-cli/releases"
                                    "/download/v0.17.1/wp-cli.phar",
                                    "/usr/bin/wp"]]
        if self.app.pargs.phpmyadmin:
            self.app.log.debug("Setting packages varible for phpMyAdmin ")
            packages = packages + [["https://github.com/phpmyadmin/phpmyadmin"
                                    "/archive/STABLE.tar.gz",
                                    "/tmp/pma.tar.gz"]]

        if self.app.pargs.adminer:
            self.app.log.debug("Setting packages variable for Adminer ")
            packages = packages + [["http://downloads.sourceforge.net/adminer"
                                    "/adminer-4.1.0.php", "/var/www/22222/"
                                    "htdocs/db/adminer/index.php"]]

        if self.app.pargs.utils:
            self.app.log.debug("Setting packages variable for utils")
            packages = packages + [["http://phpmemcacheadmin.googlecode.com/"
                                    "files/phpMemcachedAdmin-1.2.2"
                                    "-r262.tar.gz", '/tmp/memcache.tar.gz'],
                                   ["https://raw.githubusercontent.com/rtCamp/"
                                    "eeadmin/master/cache/nginx/clean.php",
                                    "/var/www/22222/htdocs/cache/"
                                    "nginx/clean.php"],
                                   ["https://raw.github.com/rlerdorf/opcache-"
                                    "status/master/opcache.php",
                                    "/var/www/22222/htdocs/cache/"
                                    "opcache/opcache.php"],
                                   ["https://raw.github.com/amnuts/opcache-gui"
                                    "/master/index.php",
                                    "/var/www/22222/htdocs/"
                                    "cache/opcache/opgui.php"],
                                   ["https://gist.github.com/ck-on/4959032/raw"
                                    "/0b871b345fd6cfcd6d2be030c1f33d1ad6a475cb"
                                    "/ocp.php",
                                    "/var/www/22222/htdocs/cache/"
                                    "opcache/ocp.php"],
                                   ["https://github.com/jokkedk/webgrind/"
                                    "archive/master.tar.gz",
                                    '/tmp/webgrind.tar.gz'],
                                   ["http://bazaar.launchpad.net/~percona-too"
                                    "lkit-dev/percona-toolkit/2.1/download/he"
                                    "ad:/ptquerydigest-20110624220137-or26tn4"
                                    "expb9ul2a-16/pt-query-digest",
                                    "/usr/bin/pt-query-advisor"],
                                   ["https://github.com/box/Anemometer/archive"
                                    "/master.tar.gz",
                                    '/tmp/anemometer.tar.gz']
                                   ]
        self.app.log.debug("Calling pre_pref ")
        self.pre_pref(apt_packages)
        if len(apt_packages):
            self.app.log.debug("Installing all apt_packages")
            pkg.install(apt_packages)
        if len(packages):
            self.app.log.debug("Downloading all packages")
            EEDownload.download(packages)
        self.app.log.debug("Calling post_pref")
        self.post_pref(apt_packages, packages)

    @expose()
    def remove(self):
        pkg = EEAptGet()
        apt_packages = []
        packages = []

        if self.app.pargs.web:
            self.app.log.debug("Removing apt_packages variable of Nginx "
                               ",PHP,MySQL")
            apt_packages = (apt_packages + EEVariables.ee_nginx +
                            EEVariables.ee_php + EEVariables.ee_mysql)
        if self.app.pargs.admin:
            pass
            # apt_packages = apt_packages + EEVariables.ee_nginx
        if self.app.pargs.mail:
            pass
            # apt_packages = apt_packages + EEVariables.ee_nginx
        if self.app.pargs.nginx:
            self.app.log.debug("Removing apt_packages variable of Nginx")
            apt_packages = apt_packages + EEVariables.ee_nginx
        if self.app.pargs.php:
            self.app.log.debug("Removing apt_packages variable of PHP")
            apt_packages = apt_packages + EEVariables.ee_php
        if self.app.pargs.mysql:
            self.app.log.debug("Removing apt_packages variable of MySQL")
            apt_packages = apt_packages + EEVariables.ee_mysql
        if self.app.pargs.postfix:
            self.app.log.debug("Removing apt_packages variable of Postfix")
            apt_packages = apt_packages + EEVariables.ee_postfix
        if self.app.pargs.wpcli:
            self.app.log.debug("Removing package variable of WPCLI ")
            packages = packages + ['/usr/bin/wp']
        if self.app.pargs.phpmyadmin:
            self.app.log.debug("Removing package variable of phpMyAdmin ")
            packages = packages + ['/var/www/22222/htdocs/db/pma']
        if self.app.pargs.adminer:
            self.app.log.debug("Removing package variable of Adminer ")
            packages = packages + ['/var/www/22222/htdocs/db/adminer']
        if self.app.pargs.utils:
            self.app.log.debug("Removing package variable of utils ")
            packages = packages + ['/var/www/22222/htdocs/php/webgrind/',
                                   '/var/www/22222/htdocs/cache/opcache',
                                   '/var/www/22222/htdocs/cache/nginx/'
                                   'clean.php',
                                   '/var/www/22222/htdocs/cache/memcache',
                                   '/usr/bin/pt-query-advisor',
                                   '/var/www/22222/htdocs/db/anemometer']

        if len(apt_packages):
            self.app.log.debug("Removing apt_packages")
            pkg.remove(apt_packages)
        if len(packages):
            EEFileUtils.remove(packages)

    @expose()
    def purge(self):
        pkg = EEAptGet()
        apt_packages = []
        packages = []

        if self.app.pargs.web:
            self.app.log.debug("Purge Nginx,PHP,MySQL")
            apt_packages = (apt_packages + EEVariables.ee_nginx
                            + EEVariables.ee_php + EEVariables.ee_mysql)
        if self.app.pargs.admin:
            pass
            # apt_packages = apt_packages + EEVariables.ee_nginx
        if self.app.pargs.mail:
            pass
            # apt_packages = apt_packages + EEVariables.ee_nginx
        if self.app.pargs.nginx:
            self.app.log.debug("Purge apt_packages variable of Nginx")
            apt_packages = apt_packages + EEVariables.ee_nginx
        if self.app.pargs.php:
            self.app.log.debug("Purge apt_packages variable PHP")
            apt_packages = apt_packages + EEVariables.ee_php
        if self.app.pargs.mysql:
            self.app.log.debug("Purge apt_packages variable MySQL")
            apt_packages = apt_packages + EEVariables.ee_mysql
        if self.app.pargs.postfix:
            self.app.log.debug("Purge apt_packages variable PostFix")
            apt_packages = apt_packages + EEVariables.ee_postfix
        if self.app.pargs.wpcli:
            self.app.log.debug("Purge package variable WPCLI")
            packages = packages + ['/usr/bin/wp']
        if self.app.pargs.phpmyadmin:
            packages = packages + ['/var/www/22222/htdocs/db/pma']
            self.app.log.debug("Purge package variable phpMyAdmin")
        if self.app.pargs.adminer:
            self.app.log.debug("Purge  package variable Adminer")
            packages = packages + ['/var/www/22222/htdocs/db/adminer']
        if self.app.pargs.utils:
            self.app.log.debug("Purge package variable utils")
            packages = packages + ['/var/www/22222/htdocs/php/webgrind/',
                                   '/var/www/22222/htdocs/cache/opcache',
                                   '/var/www/22222/htdocs/cache/nginx/'
                                   'clean.php',
                                   '/var/www/22222/htdocs/cache/memcache',
                                   '/usr/bin/pt-query-advisor',
                                   '/var/www/22222/htdocs/db/anemometer'
                                   ]

        if len(apt_packages):
            pkg.remove(apt_packages, purge=True)
        if len(packages):
            EEFileUtils.remove(packages)


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EEStackController)
    handler.register(EEStackStatusController)

    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', ee_stack_hook)
