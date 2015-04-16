from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.services import EEService
from ee.core.logging import Log
from ee.core.variables import EEVariables


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
        if self.app.pargs.nginx:
            Log.debug(self, "nginx service start")
            services = services + ['nginx']
        if self.app.pargs.php:
            Log.debug(self, "php5-fpm service start")
            services = services + ['php5-fpm']
        if self.app.pargs.mysql:
            if EEVariables.ee_mysql_host is "localhost":
                Log.debug(self, "mysql service start")
                services = services + ['mysql']
            else:
                Log.warn(self, "Remote MySQL found,"
                         "unable to start MySQL service")
        if self.app.pargs.postfix:
            Log.debug(self, "postfix service start")
            services = services + ['postfix']
        if self.app.pargs.hhvm:
            services = services + ['hhvm']
            Log.debug(self, "hhvm service start")
        if self.app.pargs.memcache:
            Log.debug(self, "memcached service start")
            services = services + ['memcached']
        if self.app.pargs.dovecot:
            Log.debug(self, "dovecot service start")
            services = services + ['dovecot']
        if not services and EEVariables.ee_mysql_host is "localhost":
            services = services + ['nginx', 'php5-fpm', 'mysql', 'postfix',
                                   'hhvm']
            Log.debug(self, "nginx,php5-fpm,mysql,postfix,hhvm services start")
        elif not services:
            services = services + ['nginx', 'php5-fpm', 'postfix', 'hhvm']
            Log.debug(self, "nginx,php5-fpm,postfix,hhvm services start")

        for service in services:
            EEService.start_service(self, service)

    @expose(help="Stop stack services")
    def stop(self):
        """Stop services"""
        services = []
        if self.app.pargs.nginx:
            Log.debug(self, "nginx service stop")
            services = services + ['nginx']
        if self.app.pargs.php:
            Log.debug(self, "php5-fpm service stop")
            services = services + ['php5-fpm']
        if self.app.pargs.mysql:
            if EEVariables.ee_mysql_host is "localhost":
                Log.debug(self, "mysql service stop")
                services = services + ['mysql']
            else:
                Log.warn(self, "Remote MySQL found, "
                               "unable to stop MySQL service")
        if self.app.pargs.postfix:
            Log.debug(self, "postfix service stop")
            services = services + ['postfix']
        if self.app.pargs.hhvm:
            services = services + ['hhvm']
            Log.debug(self, "hhvm service stop")
        if self.app.pargs.memcache:
            Log.debug(self, "memcached service stop")
            services = services + ['memcached']
        if self.app.pargs.dovecot:
            Log.debug(self, "dovecot service stop")
            services = services + ['dovecot']
        if not services and EEVariables.ee_mysql_host is "localhost":
            services = services + ['nginx', 'php5-fpm', 'mysql', 'postfix',
                                   'hhvm']
            Log.debug(self, "nginx,php5-fpm,mysql,postfix,hhvm services stop")
        elif not services:
            services = services + ['nginx', 'php5-fpm', 'postfix', 'hhvm']
            Log.debug(self, "nginx,php5-fpm,postfix,hhvm services stop")
        for service in services:
            EEService.stop_service(self, service)

    @expose(help="Restart stack services")
    def restart(self):
        """Restart services"""
        services = []
        if self.app.pargs.nginx:
            Log.debug(self, "nginx service restart")
            services = services + ['nginx']
        if self.app.pargs.php:
            Log.debug(self, "php5-fpm service restart")
            services = services + ['php5-fpm']
        if self.app.pargs.mysql:
            if EEVariables.ee_mysql_host is "localhost":
                Log.debug(self, "mysql service restart")
                services = services + ['mysql']
            else:
                Log.warn(self, "Remote MySQL found, "
                         "unable to restart MySQL service")
        if self.app.pargs.postfix:
            Log.debug(self, "postfix service restart")
            services = services + ['postfix']
        if self.app.pargs.hhvm:
            services = services + ['hhvm']
            Log.debug(self, "hhvm service restart")
        if self.app.pargs.memcache:
            Log.debug(self, "memcached service restart")
            services = services + ['memcached']
        if self.app.pargs.dovecot:
            Log.debug(self, "dovecot service restart")
            services = services + ['dovecot']
        if not services and EEVariables.ee_mysql_host is "localhost":
            services = services + ['nginx', 'php5-fpm', 'mysql', 'postfix',
                                   'hhvm']
            Log.debug(self, "nginx,php5-fpm,mysql,postfix,hhvm services"
                            "restart")
        elif not services:
            services = services + ['nginx', 'php5-fpm', 'postfix', 'hhvm']
            Log.debug(self, "nginx,php5-fpm,postfix,hhvm services restart")
        for service in services:
            EEService.restart_service(self, service)

    @expose(help="Get stack status")
    def status(self):
        """Status of services"""
        services = []
        if self.app.pargs.nginx:
            Log.debug(self, "nginx service status")
            services = services + ['nginx']
        if self.app.pargs.php:
            Log.debug(self, "php5-fpm service status")
            services = services + ['php5-fpm']
        if self.app.pargs.mysql:
            if EEVariables.ee_mysql_host is "localhost":
                Log.debug(self, "mysql service status")
                services = services + ['mysql']
            else:
                Log.warn(self, "Remote MySQL found, "
                         "unable to get MySQL service status")
        if self.app.pargs.postfix:
            services = services + ['postfix']
            Log.debug(self, "postfix service status")
        if self.app.pargs.hhvm:
            services = services + ['hhvm']
            Log.debug(self, "hhvm service status")
        if self.app.pargs.memcache:
            Log.debug(self, "memcached service status")
            services = services + ['memcached']
        if self.app.pargs.dovecot:
            Log.debug(self, "dovecot service status")
            services = services + ['dovecot']
        if not services and EEVariables.ee_mysql_host is "localhost":
            services = services + ['nginx', 'php5-fpm', 'mysql', 'postfix',
                                   'hhvm']
            Log.debug(self, "nginx,php5-fpm,mysql,postfix,hhvm services"
                            " status")
        elif not services:
            services = services + ['nginx', 'php5-fpm', 'postfix', 'hhvm']
            Log.debug(self, "nginx,php5-fpm,postfix,hhvm services status")
        for service in services:
            if EEService.get_service_status(self, service):
                Log.info(self, "{0:10}:  {1}".format(service, "Running"))

    @expose(help="Reload stack services")
    def reload(self):
        """Reload service"""
        services = []
        if self.app.pargs.nginx:
            Log.debug(self, "nginx service reload")
            services = services + ['nginx']
        if self.app.pargs.php:
            Log.debug(self, "php5-fpm service reload")
            services = services + ['php5-fpm']
        if self.app.pargs.mysql:
            if EEVariables.ee_mysql_host is "localhost":
                Log.debug(self, "mysql service reload")
                services = services + ['mysql']
            else:
                Log.warn(self, "Remote MySQL found, "
                         "unable to remote MySQL service")
        if self.app.pargs.postfix:
            Log.debug(self, "postfix service reload")
            services = services + ['postfix']
        if self.app.pargs.hhvm:
            Log.warn(self, "hhvm does not support to reload")
        if self.app.pargs.memcache:
            Log.debug(self, "memcached service reload")
            services = services + ['memcached']
        if self.app.pargs.dovecot:
            Log.debug(self, "dovecot service reload")
            services = services + ['dovecot']
        if not services and EEVariables.ee_mysql_host is "localhost":
            services = services + ['nginx', 'php5-fpm', 'mysql', 'postfix']
            Log.debug(self, "nginx,php5-fpm,mysql,postfix services reload")
        elif not services:
            services = services + ['nginx', 'php5-fpm', 'postfix']
            Log.debug(self, "nginx,php5-fpm,postfix services reload")
        for service in services:
            EEService.reload_service(self, service)
