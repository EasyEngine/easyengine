from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.services import EEService
from ee.core.logging import Log


class EEStackStatusController(CementBaseController):
    class Meta:
        label = 'stack_services'
        stacked_on = 'stack'
        stacked_type = 'embedded'
        description = 'stack command manages stack operations'
        arguments = [
            (['--memcache'],
                dict(help='start/stop/restart stack', action='store_true')),
            (['--dovecot'],
                dict(help='start/stop/restart dovecot', action='store_true')),
            ]

    @expose(help="start stack services")
    def start(self):
        services = []
        if self.app.pargs.nginx:
            Log.debug(self, "nginx service start")
            services = services + ['nginx']
        elif self.app.pargs.php:
            Log.debug(self, "php5-fpm service start")
            services = services + ['php5-fpm']
        elif self.app.pargs.mysql:
            Log.debug(self, "mysql service start")
            services = services + ['mysql']
        elif self.app.pargs.postfix:
            Log.debug(self, "postfix service start")
            services = services + ['postfix']
        elif self.app.pargs.memcache:
            Log.debug(self, "memcached service start")
            services = services + ['memcached']
        elif self.app.pargs.dovecot:
            Log.debug(self, "dovecot service start")
            services = services + ['dovecot']
        else:
            Log.debug(self, "nginx,php5-fpm,mysql,postfix services start")
            services = services + ['nginx', 'php5-fpm', 'mysql', 'postfix']
        for service in services:
            EEService.start_service(self, service)

    @expose(help="stop stack services")
    def stop(self):
        services = []
        if self.app.pargs.nginx:
            Log.debug(self, "nginx service stop")
            services = services + ['nginx']
        elif self.app.pargs.php:
            Log.debug(self, "php5-fpm service stop")
            services = services + ['php5-fpm']
        elif self.app.pargs.mysql:
            Log.debug(self, "mysql service stop")
            services = services + ['mysql']
        elif self.app.pargs.postfix:
            Log.debug(self, "postfix service stop")
            services = services + ['postfix']
        elif self.app.pargs.memcache:
            Log.debug(self, "memcached service stop")
            services = services + ['memcached']
        elif self.app.pargs.dovecot:
            Log.debug(self, "dovecot service stop")
            services = services + ['dovecot']
        else:
            services = services + ['nginx', 'php5-fpm', 'mysql', 'postfix']
            Log.debug(self, "nginx,php5-fpm,mysql,postfix services stop")
        for service in services:
            EEService.stop_service(self, service)

    @expose(help="restart stack services")
    def restart(self):
        services = []
        if self.app.pargs.nginx:
            Log.debug(self, "nginx service restart")
            services = services + ['nginx']
        elif self.app.pargs.php:
            Log.debug(self, "php5-fpm service restart")
            services = services + ['php5-fpm']
        elif self.app.pargs.mysql:
            Log.debug(self, "mysql service restart")
            services = services + ['mysql']
        elif self.app.pargs.postfix:
            Log.debug(self, "postfix service restart")
            services = services + ['postfix']
        elif self.app.pargs.memcache:
            Log.debug(self, "memcached service restart")
            services = services + ['memcached']
        elif self.app.pargs.dovecot:
            Log.debug(self, "dovecot service restart")
            services = services + ['dovecot']
        else:
            services = services + ['nginx', 'php5-fpm', 'mysql', 'postfix']
        for service in services:
            Log.debug(self, "nginx,php5-fpm,mysql,postfix services restart")
            EEService.restart_service(self, service)

    @expose(help="get stack status")
    def status(self):
        services = []
        if self.app.pargs.nginx:
            Log.debug(self, "nginx service status")
            services = services + ['nginx']
        elif self.app.pargs.php:
            Log.debug(self, "php5-fpm service status")
            services = services + ['php5-fpm']
        elif self.app.pargs.mysql:
            Log.debug(self, "mysql service status")
            services = services + ['mysql']
        elif self.app.pargs.postfix:
            services = services + ['postfix']
            Log.debug(self, "postfix service status")
        elif self.app.pargs.memcache:
            Log.debug(self, "memcached service status")
            services = services + ['memcached']
        elif self.app.pargs.dovecot:
            Log.debug(self, "dovecot service status")
            services = services + ['dovecot']
        else:
            Log.debug(self, "nginx,php5-fpm,mysql,postfix services status")
            services = services + ['nginx', 'php5-fpm', 'mysql', 'postfix']
        for service in services:
            if EEService.get_service_status(self, service):
                Log.info(self, "{0}: Running".format(service))
