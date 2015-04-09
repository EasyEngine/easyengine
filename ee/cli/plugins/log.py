"""Debug Plugin for EasyEngine"""

from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.logging import Log
from ee.cli.plugins.site_functions import logwatch
from ee.core.variables import EEVariables
from ee.core.fileutils import EEFileUtils
from ee.core.shellexec import EEShellExec
import os
import glob


def ee_log_hook(app):
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
                dict(help='Show Nginx Error logs file', action='store_true')),
            (['--php'],
                dict(help='Show PHP Error logs file', action='store_true')),
            (['--fpm'],
                dict(help='Show PHP5-fpm slow logs file',
                     action='store_true')),
            (['--mysql'],
                dict(help='Show MySQL logs file', action='store_true')),
            (['--wp'],
                dict(help='Show Site specific WordPress logs file',
                     action='store_true')),
            (['--access'],
                dict(help='Show Nginx access log file',
                     action='store_true')),
            (['site_name'],
                dict(help='Website Name', nargs='?', default=None))
            ]
        usage = "ee log [<site_name>] [options]"

    @expose(hide=True)
    def default(self):
        """Default function of debug"""
        self.msg = []

        if self.app.pargs.php:
            self.app.pargs.nginx = True

        if ((not self.app.pargs.nginx) and (not self.app.pargs.fpm)
           and (not self.app.pargs.mysql) and (not self.app.pargs.access)
           and (not self.app.pargs.wp) and (not self.app.pargs.site_name)):
            self.app.pargs.nginx = True
            self.app.pargs.fpm = True
            self.app.pargs.mysql = True
            self.app.pargs.access = True

        if ((not self.app.pargs.nginx) and (not self.app.pargs.fpm)
           and (not self.app.pargs.mysql) and (not self.app.pargs.access)
           and (not self.app.pargs.wp) and (self.app.pargs.site_name)):
            self.app.pargs.nginx = True
            self.app.pargs.wp = True
            self.app.pargs.access = True
            self.app.pargs.mysql = True

        if self.app.pargs.nginx and (not self.app.pargs.site_name):
            self.msg = self.msg + ["/var/log/nginx/*error.log"]

        if self.app.pargs.access and (not self.app.pargs.site_name):
            self.msg = self.msg + ["/var/log/nginx/*access.log"]

        if self.app.pargs.fpm:
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

        if self.app.pargs.site_name:
            if self.app.pargs.access:
                self.msg = self.msg + ["{0}/{1}/logs/access.log"
                                       .format(EEVariables.ee_webroot,
                                               self.app.pargs.site_name)]
            if self.app.pargs.nginx:
                self.msg = self.msg + ["{0}/{1}/logs/error.log"
                                       .format(EEVariables.ee_webroot,
                                               self.app.pargs.site_name)]
            if self.app.pargs.wp:
                webroot = "{0}{1}".format(EEVariables.ee_webroot,
                                          self.app.pargs.site_name)
                if not os.path.isfile('{0}/logs/debug.log'
                                      .format(webroot)):
                    if not os.path.isfile('{0}/htdocs/wp-content/debug.log'
                                          .format(webroot)):
                        open("{0}/htdocs/wp-content/debug.log".format(webroot),
                             encoding='utf-8', mode='a').close()
                        EEShellExec.cmd_exec(self, "chown {1}: {0}/htdocs/wp-"
                                             "content/debug.log"
                                             "".format(webroot,
                                                       EEVariables.ee_php_user)
                                             )

                    # create symbolic link for debug log
                    EEFileUtils.create_symlink(self, ["{0}/htdocs/wp-content/"
                                                      "debug.log"
                                                      .format(webroot),
                                                      '{0}/logs/debug.log'
                                                      .format(webroot)])

                self.msg = self.msg + ["{0}/{1}/logs/debug.log"
                                       .format(EEVariables.ee_webroot,
                                               self.app.pargs.site_name)]
        watch_list = []
        for w_list in self.msg:
            watch_list = watch_list + glob.glob(w_list)

        logwatch(self, watch_list)


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EELogController)
    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', ee_log_hook)
