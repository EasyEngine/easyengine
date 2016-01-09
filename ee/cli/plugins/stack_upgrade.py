from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.logging import Log
from ee.core.variables import EEVariables
from ee.core.aptget import EEAptGet
from ee.core.apt_repo import EERepo
from ee.core.services import EEService
from ee.core.fileutils import EEFileUtils
from ee.core.shellexec import EEShellExec
from ee.core.git import EEGit
from ee.core.download import EEDownload
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
            (['--wpcli'],
                dict(help='Upgrade WPCLI', action='store_true')),
            (['--redis'],
                dict(help='Upgrade Redis', action='store_true')),
            (['--php7.0'],
                dict(help="Upgrade to PHP7.0 from PHP5.*",
                     action='store_true')),
            (['--no-prompt'],
                dict(help="Upgrade Packages without any prompt",
                     action='store_true')),
            ]

    @expose(hide=True)
    def upgrade_php7.0(self):
        if EEVariables.ee_platform_distro == "ubuntu":
            if os.path.isfile("/etc/apt/sources.list.d/ondrej-php-{0}."
                              "list".format(EEVariables.ee_platform_codename)):
                Log.error(self, "Unable to find PHP 5.*")
        else:
            if not(os.path.isfile(EEVariables.ee_repo_file_path) and
                   EEFileUtils.grep(self, EEVariables.ee_repo_file_path,
                                    "php55")):
                Log.error(self, "Unable to find PHP 5.*")

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
                                      "php55", "php7.0")

        Log.info(self, "Updating apt-cache, please wait...")
        EEAptGet.update(self)
        Log.info(self, "Installing packages, please wait ...")
        EEAptGet.install(self, EEVariables.ee_php)

        if EEVariables.ee_platform_distro == "debian":
            EEShellExec.cmd_exec(self, "pecl install xdebug")

            with open("/etc/php/mods-available/xdebug.ini",
                      encoding='utf-8', mode='a') as myfile:
                myfile.write(";zend_extension=/usr/lib/php/7.0/20131226/"
                             "xdebug.so\n")

            EEFileUtils.create_symlink(self, ["/etc/php/mods-available/"
                                       "xdebug.ini", "/etc/php/7.0/fpm/conf.d"
                                                     "/20-xedbug.ini"])

        Log.info(self, "Successfully upgraded from PHP 5.* to PHP 5.6")

    @expose(hide=True)
    def default(self):
        # All package update
        if ((not self.app.pargs.php7.0)):

            apt_packages = []
            packages = []

            if ((not self.app.pargs.web) and (not self.app.pargs.nginx) and
               (not self.app.pargs.php) and (not self.app.pargs.mysql) and
               (not self.app.pargs.postfix) and (not self.app.pargs.hhvm) and
               (not self.app.pargs.mailscanner) and (not self.app.pargs.all)
               and (not self.app.pargs.wpcli) and (not self.app.pargs.redis)):
                self.app.pargs.web = True

            if self.app.pargs.all:
                self.app.pargs.web = True
                self.app.pargs.mail = True

            if self.app.pargs.web:
                self.app.pargs.nginx = True
                self.app.pargs.php = True
                self.app.pargs.mysql = True
                self.app.pargs.postfix = True
                self.app.pargs.wpcli = True

            if self.app.pargs.mail:
                self.app.pargs.nginx = True
                self.app.pargs.php = True
                self.app.pargs.mysql = True
                self.app.pargs.wpcli = True
                self.app.pargs.postfix = True

                if EEAptGet.is_installed(self, 'dovecot-core'):
                    apt_packages = apt_packages + EEVariables.ee_mail
                    self.app.pargs.mailscanner = True
                else:
                    Log.info(self, "Mail server is not installed")

            if self.app.pargs.nginx:
                if EEAptGet.is_installed(self, 'nginx-custom'):
                    apt_packages = apt_packages + EEVariables.ee_nginx
                else:
                    Log.info(self, "Nginx is not already installed")

            if self.app.pargs.php:
                if EEAptGet.is_installed(self, 'php7.0-fpm'):
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

            if self.app.pargs.redis:
                if EEAptGet.is_installed(self, 'redis-server'):
                    apt_packages = apt_packages + EEVariables.ee_redis
                else:
                    Log.info(self, "Redis is not installed")

            if self.app.pargs.wpcli:
                if os.path.isfile('/usr/bin/wp'):
                    packages = packages + [["https://github.com/wp-cli/wp-cli/"
                                            "releases/download/v{0}/"
                                            "wp-cli-{0}.phar"
                                            "".format(EEVariables.ee_wp_cli),
                                            "/usr/bin/wp",
                                            "WP-CLI"]]
                else:
                    Log.info(self, "WPCLI is not installed with EasyEngine")

            if self.app.pargs.mailscanner:
                if EEAptGet.is_installed(self, 'amavisd-new'):
                    apt_packages = (apt_packages + EEVariables.ee_mailscanner)
                else:
                    Log.info(self, "MailScanner is not installed")

            if len(packages) or len(apt_packages):

                Log.info(self, "During package update process non nginx-cached"
                         " parts of your site may remain down")
                # Check prompt
                if (not self.app.pargs.no_prompt):
                    start_upgrade = input("Do you want to continue:[y/N]")
                    if start_upgrade != "Y" and start_upgrade != "y":
                        Log.error(self, "Not starting package update")

                Log.info(self, "Updating packages, please wait...")
                if len(apt_packages):
                    # apt-get update
                    EEAptGet.update(self)
                    # Update packages
                    EEAptGet.install(self, apt_packages)

                    # Post Actions after package updates
                    if set(EEVariables.ee_nginx).issubset(set(apt_packages)):
                        EEService.restart_service(self, 'nginx')
                    if set(EEVariables.ee_php).issubset(set(apt_packages)):
                        EEService.restart_service(self, 'php7.0-fpm')
                    if set(EEVariables.ee_hhvm).issubset(set(apt_packages)):
                        EEService.restart_service(self, 'hhvm')
                    if set(EEVariables.ee_postfix).issubset(set(apt_packages)):
                        EEService.restart_service(self, 'postfix')
                    if set(EEVariables.ee_mysql).issubset(set(apt_packages)):
                        EEService.restart_service(self, 'hhvm')
                    if set(EEVariables.ee_mail).issubset(set(apt_packages)):
                        EEService.restart_service(self, 'dovecot')
                    if set(EEVariables.ee_redis).issubset(set(apt_packages)):
                        EEService.restart_service(self, 'redis-server')

                if len(packages):
                    if self.app.pargs.wpcli:
                        EEFileUtils.remove(self,['/usr/bin/wp'])

                    Log.debug(self, "Downloading following: {0}".format(packages))
                    EEDownload.download(self, packages)

                    if self.app.pargs.wpcli:
                        EEFileUtils.chmod(self, "/usr/bin/wp", 0o775)

                Log.info(self, "Successfully updated packages")

        # PHP 5.6 to 5.6
        elif (self.app.pargs.php7.0):
            self.upgrade_php7.0()
        else:
            self.app.args.print_help()
