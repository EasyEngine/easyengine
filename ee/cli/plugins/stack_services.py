from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.services import EEService
from ee.core.logging import Log
from ee.core.variables import EEVariables
from ee.core.aptget import EEAptGet


class EEStackStatusController(CementBaseController):
    class Meta:
        label = 'stack_services'
        stacked_on = 'stack'
        stacked_type = 'embedded'
        description = 'Get status of stack'
        arguments = [
            (['--memcache'],
                dict(help='start/stop/restart memcache', action='store_true')),
            (['--dovecot'],
                dict(help='start/stop/restart dovecot', action='store_true')),
            ]

    @expose(help="Start stack services")
    def start(self):
        """Start services"""
        services = []
        if not (self.app.pargs.nginx or self.app.pargs.php
                or self.app.pargs.mysql or self.app.pargs.postfix
                or self.app.pargs.hhvm or self.app.pargs.memcache
                or self.app.pargs.dovecot or self.app.pargs.redis):
            self.app.pargs.nginx = True
            self.app.pargs.php = True
            self.app.pargs.mysql = True
            self.app.pargs.postfix = True

        if self.app.pargs.nginx:
            if EEAptGet.is_installed(self, 'nginx-custom') or EEAptGet.is_installed(self,'nginx-mainline'):
                services = services + ['nginx']
            else:
                Log.info(self, "Nginx is not installed")

        if self.app.pargs.php:
            if EEAptGet.is_installed(self, 'php5-fpm'):
                services = services + ['php5-fpm']
            else:
                Log.info(self, "PHP5-FPM is not installed")

        if self.app.pargs.mysql:
            if ((EEVariables.ee_mysql_host is "localhost") or
               (EEVariables.ee_mysql_host is "127.0.0.1")):
                if (EEAptGet.is_installed(self, 'mysql-server') or
                   EEAptGet.is_installed(self, 'percona-server-server-5.6') or
                   EEAptGet.is_installed(self, 'mariadb-server')):
                    services = services + ['mysql']
                else:
                    Log.info(self, "MySQL is not installed")
            else:
                Log.warn(self, "Remote MySQL found, "
                         "Unable to check MySQL service status")

        if self.app.pargs.postfix:
            if EEAptGet.is_installed(self, 'postfix'):
                services = services + ['postfix']
            else:
                Log.info(self, "Postfix is not installed")

        if self.app.pargs.hhvm:
            if EEAptGet.is_installed(self, 'hhvm'):
                services = services + ['hhvm']
            else:
                Log.info(self, "HHVM is not installed")
        if self.app.pargs.memcache:
            if EEAptGet.is_installed(self, 'memcached'):
                services = services + ['memcached']
            else:
                Log.info(self, "Memcache is not installed")

        if self.app.pargs.dovecot:
            if EEAptGet.is_installed(self, 'dovecot-core'):
                services = services + ['dovecot']
            else:
                Log.info(self, "Mail server is not installed")

        if self.app.pargs.redis:
            if EEAptGet.is_installed(self, 'redis-server'):
                services = services + ['redis-server']
            else:
                Log.info(self, "Redis server is not installed")

        for service in services:
            Log.debug(self, "Starting service: {0}".format(service))
            EEService.start_service(self, service)

    @expose(help="Stop stack services")
    def stop(self):
        """Stop services"""
        services = []
        if not (self.app.pargs.nginx or self.app.pargs.php
                or self.app.pargs.mysql or self.app.pargs.postfix
                or self.app.pargs.hhvm or self.app.pargs.memcache
                or self.app.pargs.dovecot or self.app.pargs.redis):
            self.app.pargs.nginx = True
            self.app.pargs.php = True
            self.app.pargs.mysql = True
            self.app.pargs.postfix = True

        if self.app.pargs.nginx:
            if EEAptGet.is_installed(self, 'nginx-custom') or EEAptGet.is_installed(self,'nginx-mainline'):
                services = services + ['nginx']
            else:
                Log.info(self, "Nginx is not installed")

        if self.app.pargs.php:
            if EEAptGet.is_installed(self, 'php5-fpm'):
                services = services + ['php5-fpm']
            else:
                Log.info(self, "PHP5-FPM is not installed")

        if self.app.pargs.mysql:
            if ((EEVariables.ee_mysql_host is "localhost") or
               (EEVariables.ee_mysql_host is "127.0.0.1")):
                if (EEAptGet.is_installed(self, 'mysql-server') or
                   EEAptGet.is_installed(self, 'percona-server-server-5.6') or
                   EEAptGet.is_installed(self, 'mariadb-server')):
                    services = services + ['mysql']
                else:
                    Log.info(self, "MySQL is not installed")
            else:
                Log.warn(self, "Remote MySQL found, "
                         "Unable to check MySQL service status")

        if self.app.pargs.postfix:
            if EEAptGet.is_installed(self, 'postfix'):
                services = services + ['postfix']
            else:
                Log.info(self, "Postfix is not installed")

        if self.app.pargs.hhvm:
            if EEAptGet.is_installed(self, 'hhvm'):
                services = services + ['hhvm']
            else:
                Log.info(self, "HHVM is not installed")
        if self.app.pargs.memcache:
            if EEAptGet.is_installed(self, 'memcached'):
                services = services + ['memcached']
            else:
                Log.info(self, "Memcache is not installed")

        if self.app.pargs.dovecot:
            if EEAptGet.is_installed(self, 'dovecot-core'):
                services = services + ['dovecot']
            else:
                Log.info(self, "Mail server is not installed")

        if self.app.pargs.redis:
            if EEAptGet.is_installed(self, 'redis-server'):
                services = services + ['redis-server']
            else:
                Log.info(self, "Redis server is not installed")

        for service in services:
            Log.debug(self, "Stopping service: {0}".format(service))
            EEService.stop_service(self, service)

    @expose(help="Restart stack services")
    def restart(self):
        """Restart services"""
        services = []
        if not (self.app.pargs.nginx or self.app.pargs.php
                or self.app.pargs.mysql or self.app.pargs.postfix
                or self.app.pargs.hhvm or self.app.pargs.memcache
                or self.app.pargs.dovecot or self.app.pargs.redis):
            self.app.pargs.nginx = True
            self.app.pargs.php = True
            self.app.pargs.mysql = True
            self.app.pargs.postfix = True

        if self.app.pargs.nginx:
            if EEAptGet.is_installed(self, 'nginx-custom') or EEAptGet.is_installed(self,'nginx-mainline'):
                services = services + ['nginx']
            else:
                Log.info(self, "Nginx is not installed")

        if self.app.pargs.php:
            if EEAptGet.is_installed(self, 'php5-fpm'):
                services = services + ['php5-fpm']
            else:
                Log.info(self, "PHP5-FPM is not installed")

        if self.app.pargs.mysql:
            if ((EEVariables.ee_mysql_host is "localhost") or
               (EEVariables.ee_mysql_host is "127.0.0.1")):
                if (EEAptGet.is_installed(self, 'mysql-server') or
                   EEAptGet.is_installed(self, 'percona-server-server-5.6') or
                   EEAptGet.is_installed(self, 'mariadb-server')):
                    services = services + ['mysql']
                else:
                    Log.info(self, "MySQL is not installed")
            else:
                Log.warn(self, "Remote MySQL found, "
                         "Unable to check MySQL service status")

        if self.app.pargs.postfix:
            if EEAptGet.is_installed(self, 'postfix'):
                services = services + ['postfix']
            else:
                Log.info(self, "Postfix is not installed")

        if self.app.pargs.hhvm:
            if EEAptGet.is_installed(self, 'hhvm'):
                services = services + ['hhvm']
            else:
                Log.info(self, "HHVM is not installed")
        if self.app.pargs.memcache:
            if EEAptGet.is_installed(self, 'memcached'):
                services = services + ['memcached']
            else:
                Log.info(self, "Memcache is not installed")

        if self.app.pargs.dovecot:
            if EEAptGet.is_installed(self, 'dovecot-core'):
                services = services + ['dovecot']
            else:
                Log.info(self, "Mail server is not installed")

        if self.app.pargs.redis:
            if EEAptGet.is_installed(self, 'redis-server'):
                services = services + ['redis-server']
            else:
                Log.info(self, "Redis server is not installed")

        for service in services:
            Log.debug(self, "Restarting service: {0}".format(service))
            EEService.restart_service(self, service)

    @expose(help="Get stack status")
    def status(self):
        """Status of services"""
        services = []
        if not (self.app.pargs.nginx or self.app.pargs.php
                or self.app.pargs.mysql or self.app.pargs.postfix
                or self.app.pargs.hhvm or self.app.pargs.memcache
                or self.app.pargs.dovecot or self.app.pargs.redis):
            self.app.pargs.nginx = True
            self.app.pargs.php = True
            self.app.pargs.mysql = True
            self.app.pargs.postfix = True
            self.app.pargs.hhvm = True

        if self.app.pargs.nginx:
            if EEAptGet.is_installed(self, 'nginx-custom') or EEAptGet.is_installed(self,'nginx-mainline'):
                services = services + ['nginx']
            else:
                Log.info(self, "Nginx is not installed")

        if self.app.pargs.php:
            if EEAptGet.is_installed(self, 'php5-fpm'):
                services = services + ['php5-fpm']
            else:
                Log.info(self, "PHP5-FPM is not installed")

        if self.app.pargs.mysql:
            if ((EEVariables.ee_mysql_host is "localhost") or
               (EEVariables.ee_mysql_host is "127.0.0.1")):
                if (EEAptGet.is_installed(self, 'mysql-server') or
                   EEAptGet.is_installed(self, 'percona-server-server-5.6') or
                   EEAptGet.is_installed(self, 'mariadb-server')):
                    services = services + ['mysql']
                else:
                    Log.info(self, "MySQL is not installed")
            else:
                Log.warn(self, "Remote MySQL found, "
                         "Unable to check MySQL service status")

        if self.app.pargs.postfix:
            if EEAptGet.is_installed(self, 'postfix'):
                services = services + ['postfix']
            else:
                Log.info(self, "Postfix is not installed")

        if self.app.pargs.hhvm:
            if EEAptGet.is_installed(self, 'hhvm'):
                services = services + ['hhvm']
            else:
                Log.info(self, "HHVM is not installed")
        if self.app.pargs.memcache:
            if EEAptGet.is_installed(self, 'memcached'):
                services = services + ['memcached']
            else:
                Log.info(self, "Memcache is not installed")

        if self.app.pargs.dovecot:
            if EEAptGet.is_installed(self, 'dovecot-core'):
                services = services + ['dovecot']
            else:
                Log.info(self, "Mail server is not installed")

        if self.app.pargs.redis:
            if EEAptGet.is_installed(self, 'redis-server'):
                services = services + ['redis-server']
            else:
                Log.info(self, "Redis server is not installed")

        for service in services:
            if EEService.get_service_status(self, service):
                Log.info(self, "{0:10}:  {1}".format(service, "Running"))

    @expose(help="Reload stack services")
    def reload(self):
        """Reload service"""
        services = []
        if not (self.app.pargs.nginx or self.app.pargs.php
                or self.app.pargs.mysql or self.app.pargs.postfix
                or self.app.pargs.hhvm or self.app.pargs.memcache
                or self.app.pargs.dovecot or self.app.pargs.redis):
            self.app.pargs.nginx = True
            self.app.pargs.php = True
            self.app.pargs.mysql = True
            self.app.pargs.postfix = True

        if self.app.pargs.nginx:
            if EEAptGet.is_installed(self, 'nginx-custom') or EEAptGet.is_installed(self,'nginx-mainline'):
                services = services + ['nginx']
            else:
                Log.info(self, "Nginx is not installed")

        if self.app.pargs.php:
            if EEAptGet.is_installed(self, 'php5-fpm'):
                services = services + ['php5-fpm']
            else:
                Log.info(self, "PHP5-FPM is not installed")

        if self.app.pargs.mysql:
            if ((EEVariables.ee_mysql_host is "localhost") or
               (EEVariables.ee_mysql_host is "127.0.0.1")):
                if (EEAptGet.is_installed(self, 'mysql-server') or
                   EEAptGet.is_installed(self, 'percona-server-server-5.6') or
                   EEAptGet.is_installed(self, 'mariadb-server')):
                    services = services + ['mysql']
                else:
                    Log.info(self, "MySQL is not installed")
            else:
                Log.warn(self, "Remote MySQL found, "
                         "Unable to check MySQL service status")

        if self.app.pargs.postfix:
            if EEAptGet.is_installed(self, 'postfix'):
                services = services + ['postfix']
            else:
                Log.info(self, "Postfix is not installed")

        if self.app.pargs.hhvm:
            Log.info(self, "HHVM does not support to reload")

        if self.app.pargs.memcache:
            if EEAptGet.is_installed(self, 'memcached'):
                services = services + ['memcached']
            else:
                Log.info(self, "Memcache is not installed")

        if self.app.pargs.dovecot:
            if EEAptGet.is_installed(self, 'dovecot-core'):
                services = services + ['dovecot']
            else:
                Log.info(self, "Mail server is not installed")

        if self.app.pargs.redis:
            if EEAptGet.is_installed(self, 'redis-server'):
                services = services + ['redis-server']
            else:
                Log.info(self, "Redis server is not installed")

        for service in services:
            Log.debug(self, "Reloading service: {0}".format(service))
            EEService.reload_service(self, service)
