from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.mysql import EEMysql
from ee.core.logging import Log
from ee.core.variables import EEVariables
from ee.core.aptget import EEAptGet
from ee.core.shellexec import EEShellExec
from ee.core.apt_repo import EERepo
from ee.core.services import EEService
import configparser
import os


class EEStackMigrateController(CementBaseController):
    class Meta:
        label = 'migrate'
        stacked_on = 'stack'
        stacked_type = 'nested'
        description = ('Migrate stack safely')
        arguments = [
            (['--mariadb'],
                dict(help="Migrate database to MariaDB",
                     action='store_true')),
            # (['--PHP'],
            #    dict(help="update to html site", action='store_true')),
            ]

    @expose(hide=True)
    def migrate_mariadb(self):
        # Backup all database
        EEMysql.backupAll(self)

        # Add MariaDB repo
        Log.info(self, "Adding repository for MariaDB, please wait...")

        mysql_pref = ("Package: *\nPin: origin sfo1.mirrors.digitalocean.com"
                      "\nPin-Priority: 1000\n")
        with open('/etc/apt/preferences.d/'
                  'MariaDB.pref', 'w') as mysql_pref_file:
            mysql_pref_file.write(mysql_pref)

        EERepo.add(self, repo_url=EEVariables.ee_mysql_repo)
        Log.debug(self, 'Adding key for {0}'
                  .format(EEVariables.ee_mysql_repo))
        EERepo.add_key(self, '0xcbcb082a1bb943db',
                       keyserver="keyserver.ubuntu.com")

        config = configparser.ConfigParser()
        if os.path.exists('/etc/mysql/conf.d/my.cnf'):
            config.read('/etc/mysql/conf.d/my.cnf')
        else:
            config.read(os.path.expanduser("~")+'/.my.cnf')

        try:
            chars = config['client']['password']
        except Exception as e:
            Log.error(self, "Error: process exited with error %s"
                            % e)

        Log.debug(self, "Pre-seeding MariaDB")
        Log.debug(self, "echo \"mariadb-server-10.0 "
                        "mysql-server/root_password "
                        "password \" | "
                        "debconf-set-selections")
        EEShellExec.cmd_exec(self, "echo \"mariadb-server-10.0 "
                                   "mysql-server/root_password "
                                   "password {chars}\" | "
                                   "debconf-set-selections"
                                   .format(chars=chars),
                                   log=False)
        Log.debug(self, "echo \"mariadb-server-10.0 "
                        "mysql-server/root_password_again "
                        "password \" | "
                        "debconf-set-selections")
        EEShellExec.cmd_exec(self, "echo \"mariadb-server-10.0 "
                                   "mysql-server/root_password_again "
                                   "password {chars}\" | "
                                   "debconf-set-selections"
                                   .format(chars=chars),
                                   log=False)

        # Install MariaDB
        apt_packages = EEVariables.ee_mysql

        # If PHP is installed then install php5-mysql
        if EEAptGet.is_installed(self, "php5-fpm"):
            apt_packages = apt_packages + ["php5-mysql"]

        # If mail server is installed then install dovecot-sql and postfix-sql
        if EEAptGet.is_installed(self, "dovecot-core"):
            apt_packages = apt_packages + ["dovecot-mysql", "postfix-mysql",
                                           "libclass-dbi-mysql-perl"]

        Log.info(self, "Updating apt-cache, please wait...")
        EEAptGet.update(self)
        Log.info(self, "Installing MariaDB, please wait...")
        EEAptGet.remove(self, ["mysql-common", "libmysqlclient18"])
        EEAptGet.auto_remove(self)
        EEAptGet.install(self, apt_packages)

        # Restart  dovecot and postfix if installed
        if EEAptGet.is_installed(self, "dovecot-core"):
            EEService.restart_service(self, 'dovecot')
            EEService.restart_service(self, 'postfix')

    @expose(hide=True)
    def default(self):
        if ((not self.app.pargs.mariadb)):
            self.app.args.print_help()
        if self.app.pargs.mariadb:
            if EEVariables.ee_mysql_host is not "localhost":
                Log.error(self, "Remote MySQL found, EasyEngine will not "
                          "install MariaDB")

            if EEShellExec.cmd_exec(self, "mysqladmin ping") and (not
               EEAptGet.is_installed(self, 'mariadb-server')):

                Log.info(self, "If your database size is big, "
                         "migration may take some time.")
                Log.info(self, "During migration non nginx-cached parts of "
                         "your site may remain down")
                start_migrate = input("Type \"mariadb\" to continue:")
                if start_migrate != "mariadb":
                    Log.error(self, "Not starting migration")
                self.migrate_mariadb()
            else:
                Log.error(self, "Your current MySQL is not alive or "
                          "you allready installed MariaDB")
