"""Debug Plugin for EasyEngine"""

from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.logging import Log
from ee.cli.plugins.site_functions import logwatch
from ee.core.variables import EEVariables
from ee.core.fileutils import EEFileUtils
from ee.core.shellexec import EEShellExec
from ee.core.sendmail import EESendMail
from ee.core.mysql import EEMysql
import os
import glob
import gzip


def ee_log_hook(app):
    # do something with the ``app`` object here.
    pass


class EELogController(CementBaseController):
    class Meta:
        label = 'log'
        description = 'Perform operations on Nginx, PHP, MySQL log file'
        stacked_on = 'base'
        stacked_type = 'nested'
        usage = "ee log [<site_name>] [options]"

    @expose(hide=True)
    def default(self):
        self.app.args.print_help()


class EELogShowController(CementBaseController):
    class Meta:
        label = 'show'
        description = 'Show Nginx, PHP, MySQL log file'
        stacked_on = 'log'
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
        usage = "ee log show [<site_name>] [options]"

    @expose(hide=True)
    def default(self):
        """Default function of log show"""
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
                    Log.info(self, "MySQL slow-log not found, skipped")
            else:
                Log.warn(self, "Remote MySQL found, EasyEngine is not able to"
                         "show MySQL log file")

        if self.app.pargs.site_name:
            webroot = "{0}{1}".format(EEVariables.ee_webroot,
                                      self.app.pargs.site_name)

            if not os.path.isdir(webroot):
                Log.error(self, "Site not present, quitting")
            if self.app.pargs.access:
                self.msg = self.msg + ["{0}/{1}/logs/access.log"
                                       .format(EEVariables.ee_webroot,
                                               self.app.pargs.site_name)]
            if self.app.pargs.nginx:
                self.msg = self.msg + ["{0}/{1}/logs/error.log"
                                       .format(EEVariables.ee_webroot,
                                               self.app.pargs.site_name)]
            if self.app.pargs.wp:
                if os.path.isdir('{0}/htdocs/wp-content'.format(webroot)):
                    if not os.path.isfile('{0}/logs/debug.log'
                                          .format(webroot)):
                        if not os.path.isfile('{0}/htdocs/wp-content/debug.log'
                                              .format(webroot)):
                            open("{0}/htdocs/wp-content/debug.log"
                                 .format(webroot),
                                 encoding='utf-8', mode='a').close()
                            EEShellExec.cmd_exec(self, "chown {1}: {0}/htdocs/"
                                                 "wp-content/debug.log"
                                                 "".format(webroot,
                                                           EEVariables
                                                           .ee_php_user)
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
                else:
                    Log.info(self, "Site is not WordPress site, skipping "
                             "WordPress logs")

        watch_list = []
        for w_list in self.msg:
            watch_list = watch_list + glob.glob(w_list)

        logwatch(self, watch_list)


class EELogResetController(CementBaseController):
    class Meta:
        label = 'reset'
        description = 'Reset Nginx, PHP, MySQL log file'
        stacked_on = 'log'
        stacked_type = 'nested'
        arguments = [
            (['--all'],
                dict(help='Reset All logs file', action='store_true')),
            (['--nginx'],
                dict(help='Reset Nginx Error logs file', action='store_true')),
            (['--php'],
                dict(help='Reset PHP Error logs file', action='store_true')),
            (['--fpm'],
                dict(help='Reset PHP5-fpm slow logs file',
                     action='store_true')),
            (['--mysql'],
                dict(help='Reset MySQL logs file', action='store_true')),
            (['--wp'],
                dict(help='Reset Site specific WordPress logs file',
                     action='store_true')),
            (['--access'],
                dict(help='Reset Nginx access log file',
                     action='store_true')),
            (['--slow-log-db'],
                dict(help='Drop all rows from slowlog table in database',
                     action='store_true')),
            (['site_name'],
                dict(help='Website Name', nargs='?', default=None))
            ]
        usage = "ee log reset [<site_name>] [options]"

    @expose(hide=True)
    def default(self):
        """Default function of log reset"""
        self.msg = []

        if self.app.pargs.php:
            self.app.pargs.nginx = True

        if ((not self.app.pargs.nginx) and (not self.app.pargs.fpm)
           and (not self.app.pargs.mysql) and (not self.app.pargs.access)
           and (not self.app.pargs.wp) and (not self.app.pargs.site_name)
           and (not self.app.pargs.slow_log_db)):
            self.app.pargs.nginx = True
            self.app.pargs.fpm = True
            self.app.pargs.mysql = True
            self.app.pargs.access = True
            self.app.pargs.slow_log_db = True

        if ((not self.app.pargs.nginx) and (not self.app.pargs.fpm)
           and (not self.app.pargs.mysql) and (not self.app.pargs.access)
           and (not self.app.pargs.wp) and (self.app.pargs.site_name)
           and (not self.app.pargs.slow-log-db)):
            self.app.pargs.nginx = True
            self.app.pargs.wp = True
            self.app.pargs.access = True
            self.app.pargs.mysql = True

        if self.app.pargs.slow_log_db:
            if os.path.isdir("/var/www/22222/htdocs/db/anemometer"):
                Log.info(self, "Resetting MySQL slow_query_log database table")
                EEMysql.execute(self, "TRUNCATE TABLE  "
                                "slow_query_log.global_query_review_history")
                EEMysql.execute(self, "TRUNCATE TABLE "
                                "slow_query_log.global_query_review")

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
                    Log.info(self, "MySQL slow-log not found, skipped")
            else:
                Log.warn(self, "Remote MySQL found, EasyEngine is not able to"
                         "show MySQL log file")

        if self.app.pargs.site_name:
            webroot = "{0}{1}".format(EEVariables.ee_webroot,
                                      self.app.pargs.site_name)

            if not os.path.isdir(webroot):
                Log.error(self, "Site not present, quitting")
            if self.app.pargs.access:
                self.msg = self.msg + ["{0}/{1}/logs/access.log"
                                       .format(EEVariables.ee_webroot,
                                               self.app.pargs.site_name)]
            if self.app.pargs.nginx:
                self.msg = self.msg + ["{0}/{1}/logs/error.log"
                                       .format(EEVariables.ee_webroot,
                                               self.app.pargs.site_name)]
            if self.app.pargs.wp:
                if os.path.isdir('{0}/htdocs/wp-content'.format(webroot)):
                    if not os.path.isfile('{0}/logs/debug.log'
                                          .format(webroot)):
                        if not os.path.isfile('{0}/htdocs/wp-content/debug.log'
                                              .format(webroot)):
                            open("{0}/htdocs/wp-content/debug.log"
                                 .format(webroot),
                                 encoding='utf-8', mode='a').close()
                            EEShellExec.cmd_exec(self, "chown {1}: {0}/htdocs/"
                                                 "wp-content/debug.log"
                                                 "".format(webroot,
                                                           EEVariables
                                                           .ee_php_user)
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
                else:
                    Log.info(self, "Site is not WordPress site, skipping "
                             "WordPress logs")

        reset_list = []
        for r_list in self.msg:
            reset_list = reset_list + glob.glob(r_list)

        # Clearing content of file
        for r_list in reset_list:
            Log.info(self, "Resetting file {file}".format(file=r_list))
            open(r_list, 'w').close()


class EELogGzipController(CementBaseController):
    class Meta:
        label = 'gzip'
        description = 'GZip Nginx, PHP, MySQL log file'
        stacked_on = 'log'
        stacked_type = 'nested'
        arguments = [
            (['--all'],
                dict(help='GZip All logs file', action='store_true')),
            (['--nginx'],
                dict(help='GZip Nginx Error logs file', action='store_true')),
            (['--php'],
                dict(help='GZip PHP Error logs file', action='store_true')),
            (['--fpm'],
                dict(help='GZip PHP5-fpm slow logs file',
                     action='store_true')),
            (['--mysql'],
                dict(help='GZip MySQL logs file', action='store_true')),
            (['--wp'],
                dict(help='GZip Site specific WordPress logs file',
                     action='store_true')),
            (['--access'],
                dict(help='GZip Nginx access log file',
                     action='store_true')),
            (['site_name'],
                dict(help='Website Name', nargs='?', default=None))
            ]
        usage = "ee log gzip [<site_name>] [options]"

    @expose(hide=True)
    def default(self):
        """Default function of log GZip"""
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
                    Log.info(self, "MySQL slow-log not found, skipped")

            else:
                Log.warn(self, "Remote MySQL found, EasyEngine is not able to"
                         "show MySQL log file")

        if self.app.pargs.site_name:
            webroot = "{0}{1}".format(EEVariables.ee_webroot,
                                      self.app.pargs.site_name)

            if not os.path.isdir(webroot):
                Log.error(self, "Site not present, quitting")
            if self.app.pargs.access:
                self.msg = self.msg + ["{0}/{1}/logs/access.log"
                                       .format(EEVariables.ee_webroot,
                                               self.app.pargs.site_name)]
            if self.app.pargs.nginx:
                self.msg = self.msg + ["{0}/{1}/logs/error.log"
                                       .format(EEVariables.ee_webroot,
                                               self.app.pargs.site_name)]
            if self.app.pargs.wp:
                if os.path.isdir('{0}/htdocs/wp-content'.format(webroot)):
                    if not os.path.isfile('{0}/logs/debug.log'
                                          .format(webroot)):
                        if not os.path.isfile('{0}/htdocs/wp-content/debug.log'
                                              .format(webroot)):
                            open("{0}/htdocs/wp-content/debug.log"
                                 .format(webroot),
                                 encoding='utf-8', mode='a').close()
                            EEShellExec.cmd_exec(self, "chown {1}: {0}/htdocs/"
                                                 "wp-content/debug.log"
                                                 "".format(webroot,
                                                           EEVariables
                                                           .ee_php_user)
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
                else:
                    Log.info(self, "Site is not WordPress site, skipping "
                             "WordPress logs")

        gzip_list = []
        for g_list in self.msg:
            gzip_list = gzip_list + glob.glob(g_list)

        # Gzip content of file
        for g_list in gzip_list:
            Log.info(self, "Gzipping file {file}".format(file=g_list))
            in_file = g_list
            in_data = open(in_file, "rb").read()
            out_gz = g_list + ".gz"
            gzf = gzip.open(out_gz, "wb")
            gzf.write(in_data)
            gzf.close()


class EELogMailController(CementBaseController):
    class Meta:
        label = 'mail'
        description = 'Mail Nginx, PHP, MySQL log file'
        stacked_on = 'log'
        stacked_type = 'nested'
        arguments = [
            (['--all'],
                dict(help='Mail All logs file', action='store_true')),
            (['--nginx'],
                dict(help='Mail Nginx Error logs file', action='store_true')),
            (['--php'],
                dict(help='Mail PHP Error logs file', action='store_true')),
            (['--fpm'],
                dict(help='Mail PHP5-fpm slow logs file',
                     action='store_true')),
            (['--mysql'],
                dict(help='Mail MySQL logs file', action='store_true')),
            (['--wp'],
                dict(help='Mail Site specific WordPress logs file',
                     action='store_true')),
            (['--access'],
                dict(help='Mail Nginx access log file',
                     action='store_true')),
            (['site_name'],
                dict(help='Website Name', nargs='?', default=None)),
            (['--to'],
             dict(help='EMail addresses to send log files', action='append',
                  dest='to', nargs=1, required=True)),
            ]
        usage = "ee log mail [<site_name>] [options]"

    @expose(hide=True)
    def default(self):
        """Default function of log Mail"""
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
                    Log.info(self, "MySQL slow-log not found, skipped")
            else:
                Log.warn(self, "Remote MySQL found, EasyEngine is not able to"
                         "show MySQL log file")

        if self.app.pargs.site_name:
            webroot = "{0}{1}".format(EEVariables.ee_webroot,
                                      self.app.pargs.site_name)

            if not os.path.isdir(webroot):
                Log.error(self, "Site not present, quitting")
            if self.app.pargs.access:
                self.msg = self.msg + ["{0}/{1}/logs/access.log"
                                       .format(EEVariables.ee_webroot,
                                               self.app.pargs.site_name)]
            if self.app.pargs.nginx:
                self.msg = self.msg + ["{0}/{1}/logs/error.log"
                                       .format(EEVariables.ee_webroot,
                                               self.app.pargs.site_name)]
            if self.app.pargs.wp:
                if os.path.isdir('{0}/htdocs/wp-content'.format(webroot)):
                    if not os.path.isfile('{0}/logs/debug.log'
                                          .format(webroot)):
                        if not os.path.isfile('{0}/htdocs/wp-content/debug.log'
                                              .format(webroot)):
                            open("{0}/htdocs/wp-content/debug.log"
                                 .format(webroot),
                                 encoding='utf-8', mode='a').close()
                            EEShellExec.cmd_exec(self, "chown {1}: {0}/htdocs/"
                                                 "wp-content/debug.log"
                                                 "".format(webroot,
                                                           EEVariables
                                                           .ee_php_user)
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
                else:
                    Log.info(self, "Site is not WordPress site, skipping "
                             "WordPress logs")

        mail_list = []
        for m_list in self.msg:
            mail_list = mail_list + glob.glob(m_list)

        for tomail in self.app.pargs.to:
            Log.info(self, "Sending mail to {0}".format(tomail[0]))
            EESendMail("easyengine", tomail[0], "{0} Log Files"
                       .format(EEVariables.ee_fqdn),
                       "Hey Hi,\n  Please find attached server log files"
                       "\n\n\nYour's faithfully,\nEasyEngine",
                       files=mail_list, port=25, isTls=False)


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EELogController)
    handler.register(EELogShowController)
    handler.register(EELogResetController)
    handler.register(EELogGzipController)
    handler.register(EELogMailController)
    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', ee_log_hook)
