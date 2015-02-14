from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.services import EEService
from ee.core.logging import Log


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
            Log.debug(self, "mysql service start")
            services = services + ['mysql']
        if self.app.pargs.postfix:
            Log.debug(self, "postfix service start")
            services = services + ['postfix']
        if self.app.pargs.memcache:
            Log.debug(self, "memcached service start")
            services = services + ['memcached']
        if self.app.pargs.dovecot:
            Log.debug(self, "dovecot service start")
            services = services + ['dovecot']
        if not services:
            Log.debug(self, "nginx,php5-fpm,mysql,postfix services start")
            services = services + ['nginx', 'php5-fpm', 'mysql', 'postfix']
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
            Log.debug(self, "mysql service stop")
            services = services + ['mysql']
        if self.app.pargs.postfix:
            Log.debug(self, "postfix service stop")
            services = services + ['postfix']
        if self.app.pargs.memcache:
            Log.debug(self, "memcached service stop")
            services = services + ['memcached']
        if self.app.pargs.dovecot:
            Log.debug(self, "dovecot service stop")
            services = services + ['dovecot']
        if not services:
            services = services + ['nginx', 'php5-fpm', 'mysql', 'postfix']
            Log.debug(self, "nginx,php5-fpm,mysql,postfix services stop")
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
            Log.debug(self, "mysql service restart")
            services = services + ['mysql']
        if self.app.pargs.postfix:
            Log.debug(self, "postfix service restart")
            services = services + ['postfix']
        if self.app.pargs.memcache:
            Log.debug(self, "memcached service restart")
            services = services + ['memcached']
        if self.app.pargs.dovecot:
            Log.debug(self, "dovecot service restart")
            services = services + ['dovecot']
        if not services:
            services = services + ['nginx', 'php5-fpm', 'mysql', 'postfix']
        for service in services:
            Log.debug(self, "nginx,php5-fpm,mysql,postfix services restart")
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
            Log.debug(self, "mysql service status")
            services = services + ['mysql']
        if self.app.pargs.postfix:
            services = services + ['postfix']
            Log.debug(self, "postfix service status")
        if self.app.pargs.memcache:
            Log.debug(self, "memcached service status")
            services = services + ['memcached']
        if self.app.pargs.dovecot:
            Log.debug(self, "dovecot service status")
            services = services + ['dovecot']
        if not services:
            Log.debug(self, "nginx,php5-fpm,mysql,postfix services status")
            services = services + ['nginx', 'php5-fpm', 'mysql', 'postfix']
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
            Log.debug(self, "mysql service reload")
            services = services + ['mysql']
        if self.app.pargs.postfix:
            Log.debug(self, "postfix service reload")
            services = services + ['postfix']
        if self.app.pargs.memcache:
            Log.debug(self, "memcached service reload")
            services = services + ['memcached']
        if self.app.pargs.dovecot:
            Log.debug(self, "dovecot service reload")
            services = services + ['dovecot']
        if not services:
            services = services + ['nginx', 'php5-fpm', 'mysql', 'postfix']
        for service in services:
            Log.debug(self, "nginx,php5-fpm,mysql,postfix services reload")
            EEService.reload_service(self, service)
