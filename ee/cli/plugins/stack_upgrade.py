from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.logging import Log
from ee.core.variables import EEVariables
from ee.core.aptget import EEAptGet
from ee.core.apt_repo import EERepo
from ee.core.services import EEService
from ee.core.fileutils import EEFileUtils
from ee.core.shellexec import EEShellExec
import configparser
import os


class EEStackUpgradeController(CementBaseController):
    class Meta:
        label = 'upgrade'
        stacked_on = 'stack'
        stacked_type = 'nested'
        description = ('Upgrade stack safely')
        arguments = [
            (['--all'],
                dict(help='Upgrade all stack', action='store_true')),
            (['--web'],
                dict(help='Upgrade web stack', action='store_true')),
            (['--admin'],
                dict(help='Upgrade admin tools stack', action='store_true')),
            (['--mail'],
                dict(help='Upgrade mail server stack', action='store_true')),
            (['--mailscanner'],
                dict(help='Upgrade mail scanner stack', action='store_true')),
            (['--nginx'],
                dict(help='Upgrade Nginx stack', action='store_true')),
            (['--php'],
                dict(help='Upgrade PHP stack', action='store_true')),
            (['--mysql'],
                dict(help='Upgrade MySQL stack', action='store_true')),
            (['--hhvm'],
                dict(help='Upgrade HHVM stack', action='store_true')),
            (['--postfix'],
                dict(help='Upgrade Postfix stack', action='store_true')),
            (['--php56'],
                dict(help="Upgrade to PHP5.6 from PHP5.5",
                     action='store_true')),
            (['--no-prompt'],
                dict(help="Upgrade Packages without any prompt",
                     action='store_true')),
            ]

    @expose(hide=True)
    def upgrade_php56(self):
        if EEVariables.ee_platform_distro == "ubuntu":
            if os.path.isfile("/etc/apt/sources.list.d/ondrej-php5-5_6-{0}."
                              "list".format(EEVariables.ee_platform_codename)):
                Log.error(self, "Unable to find PHP 5.5")
        else:
            if not(os.path.isfile(EEVariables.ee_repo_file_path) and
                   EEFileUtils.grep(self, EEVariables.ee_repo_file_path,
                                    "php55")):
                Log.error(self, "Unable to find PHP 5.5")

        Log.info(self, "During PHP update process non nginx-cached"
                 " parts of your site may remain down")

        # Check prompt
        if (not self.app.pargs.no_prompt):
            start_upgrade = input("Do you want to continue:[y/N]")
            if start_upgrade != "Y" and start_upgrade != "y":
                Log.error(self, "Not starting PHP package update")

        if EEVariables.ee_platform_distro == "ubuntu":
            EERepo.remove(self, ppa="ppa:ondrej/php5")
            EERepo.add(self, ppa=EEVariables.ee_php_repo)
        else:
            EEAptGet.remove(self, ["php5-xdebug"])
            EEFileUtils.searchreplace(self, EEVariables.ee_repo_file_path,
                                      "php55", "php56")

        Log.info(self, "Updating apt-cache, please wait...")
        EEAptGet.update(self)
        Log.info(self, "Installing packages, please wait ...")
        EEAptGet.install(self, EEVariables.ee_php)

        if EEVariables.ee_platform_distro == "debian":
            EEShellExec.cmd_exec(self, "pecl install xdebug")

            with open("/etc/php5/mods-available/xdebug.ini",
                      encoding='utf-8', mode='a') as myfile:
                myfile.write(";zend_extension=/usr/lib/php5/20131226/"
                             "xdebug.so\n")

            EEFileUtils.create_symlink(self, ["/etc/php5/mods-available/"
                                       "xdebug.ini", "/etc/php5/fpm/conf.d"
                                                     "/20-xedbug.ini"])

        Log.info(self, "Successfully upgraded from PHP 5.5 to PHP 5.6")

    @expose(hide=True)
    def default(self):
        # All package update
        if ((not self.app.pargs.php56)):

            apt_packages = []

            Log.info(self, "During package update process non nginx-cached"
                     " parts of your site may remain down")
            # Check prompt
            if (not self.app.pargs.no_prompt):
                start_upgrade = input("Do you want to continue:[y/N]")
                if start_upgrade != "Y" and start_upgrade != "y":
                    Log.error(self, "Not starting package update")

            if ((not self.app.pargs.web) and (not self.app.pargs.nginx) and
               (not self.app.pargs.php) and (not self.app.pargs.mysql) and
               (not self.app.pargs.postfix) and (not self.app.pargs.hhvm) and
               (not self.app.pargs.mailscanner) and (not self.app.pargs.all)):
                self.app.pargs.web = True

            if self.app.pargs.all:
                self.app.pargs.web = True
                self.app.pargs.mail = True

            if self.app.pargs.web:
                self.app.pargs.nginx = True
                self.app.pargs.php = True
                self.app.pargs.mysql = True
                self.app.pargs.postfix = True
                self.app.pargs.hhvm = True

            if self.app.pargs.mail:
                self.app.pargs.nginx = True
                self.app.pargs.php = True
                self.app.pargs.mysql = True
                self.app.pargs.postfix = True

                if EEAptGet.is_installed(self, 'dovecot-core'):
                    apt_packages = apt_packages + EEVariables.ee_mail
                    self.app.pargs.mailscanner = True
                else:
                    Log.info(self, "Mail server is not installed")

            if self.app.pargs.nginx:
                if EEVariables.ee_platform_distro == 'debian':
                    check_nginx = 'nginx-extras'
                else:
                    check_nginx = 'nginx-custom'

                if EEAptGet.is_installed(self, check_nginx):
                    apt_packages = apt_packages + EEVariables.ee_nginx
                else:
                    Log.info(self, "Nginx is not already installed")

            if self.app.pargs.php:
                if EEAptGet.is_installed(self, 'php5-fpm'):
                    apt_packages = apt_packages + EEVariables.ee_php
                else:
                    Log.info(self, "PHP is not installed")

            if self.app.pargs.hhvm:
                if EEAptGet.is_installed(self, 'hhvm'):
                    apt_packages = apt_packages + EEVariables.ee_hhvm
                else:
                    Log.info(self, "HHVM is not installed")

            if self.app.pargs.mysql:
                if EEAptGet.is_installed(self, 'mariadb-server'):
                    apt_packages = apt_packages + EEVariables.ee_mysql
                else:
                    Log.info(self, "MariaDB is not installed")

            if self.app.pargs.postfix:
                if EEAptGet.is_installed(self, 'postfix'):
                    apt_packages = apt_packages + EEVariables.ee_postfix
                else:
                    Log.info(self, "Postfix is not installed")

            if self.app.pargs.mailscanner:
                if EEAptGet.is_installed(self, 'amavisd-new'):
                    apt_packages = (apt_packages + EEVariables.ee_mailscanner)
                else:
                    Log.info(self, "MailScanner is not installed")

            if len(apt_packages):
                # apt-get update
                EEAptGet.update(self)

                # Update packages
                Log.info(self, "Updating packages, please wait...")
                EEAptGet.install(self, apt_packages)
                Log.info(self, "Successfully updated packages")

            # Post Actions after package updates
            if set(EEVariables.ee_nginx).issubset(set(apt_packages)):
                # Fix whitescreen of death beacuse of missing value
                # fastcgi_param SCRIPT_FILENAME $request_filename; in file
                # /etc/nginx/fastcgi_params
                if not EEFileUtils.grep(self, '/etc/nginx/fastcgi_params',
                                        'SCRIPT_FILENAME'):
                    with open('/etc/nginx/fastcgi_params',
                              encoding='utf-8', mode='a') as ee_nginx:
                        ee_nginx.write('fastcgi_param \tSCRIPT_FILENAME '
                                       '\t$request_filename;\n')

                EEService.restart_service(self, 'nginx')

            if set(EEVariables.ee_php).issubset(set(apt_packages)):
                EEService.restart_service(self, 'php')
            if set(EEVariables.ee_hhvm).issubset(set(apt_packages)):
                EEService.restart_service(self, 'hhvm')
            if set(EEVariables.ee_postfix).issubset(set(apt_packages)):
                EEService.restart_service(self, 'postfix')
            if set(EEVariables.ee_mysql).issubset(set(apt_packages)):
                EEService.restart_service(self, 'hhvm')
            if set(EEVariables.ee_mail).issubset(set(apt_packages)):
                EEService.restart_service(self, 'dovecot')

        # PHP 5.6 to 5.6
        elif (self.app.pargs.php56):
            self.upgrade_php56()
        else:
            self.app.args.print_help()
