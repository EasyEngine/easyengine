"""Debug Plugin for EasyEngine"""

from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.logging import Log
from ee.cli.plugins.site_functions import logwatch
from ee.core.variables import EEVariables
import os
import glob


def log_plugin_hook(app):
    # do something with the ``app`` object here.
    pass


class EELogController(CementBaseController):
    class Meta:
        label = 'log'
        description = 'Show Nginx, PHP, MySQL log file'
        stacked_on = 'base'
        stacked_type = 'nested'
        arguments = [
            (['--all'],
                dict(help='Show All logs file', action='store_true')),
            (['--nginx'],
                dict(help='Show Nginx logs file', action='store_true')),
            (['--php'],
                dict(help='Show PHP logs file', action='store_true')),
            (['--mysql'],
                dict(help='Show MySQL logs file', action='store_true')),
            ]

    @expose(hide=True)
    def default(self):
        """Default function of debug"""
        self.msg = []

        if ((not self.app.pargs.nginx) and (not self.app.pargs.php)
           and (not self.app.pargs.mysql)):
            self.app.pargs.nginx = True
            self.app.pargs.php = True
            self.app.pargs.mysql = True

        if self.app.pargs.nginx:
            self.msg = self.msg + ["/var/log/nginx/*error.log"]
        if self.app.pargs.php:
            open('/var/log/php5/slow.log', 'a').close()
            open('/var/log/php5/fpm.log', 'a').close()
            self.msg = self.msg + ['/var/log/php5/slow.log',
                                   '/var/log/php5/fpm.log']
        if self.app.pargs.mysql:
            # MySQL debug will not work for remote MySQL
            if EEVariables.ee_mysql_host is "localhost":
                if os.path.isfile('/var/log/mysql/mysql-slow.log'):
                    self.msg = self.msg + ['/var/log/mysql/mysql-slow.log']
                else:
                    Log.error(self, "Unable to find MySQL slow log file,"
                              "Please generate it using commnad ee debug "
                              "--mysql")
            else:
                Log.warn(self, "Remote MySQL found, EasyEngine is not able to"
                         "show MySQL log file")

        watch_list = []
        for w_list in self.msg:
            watch_list = watch_list + glob.glob(w_list)

        logwatch(self, watch_list)


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EELogController)
    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', log_plugin_hook)
