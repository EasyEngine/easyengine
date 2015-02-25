"""Debug Plugin for EasyEngine"""

from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.shellexec import EEShellExec
from ee.core.mysql import EEMysql
from ee.core.services import EEService
from ee.core.logging import Log
from ee.cli.plugins.site_functions import logwatch
from ee.core.variables import EEVariables
import os
import configparser
import glob
import signal
import subprocess


def debug_plugin_hook(app):
    # do something with the ``app`` object here.
    pass


class EEDebugController(CementBaseController):
    class Meta:
        label = 'debug'
        description = 'Used for server level debugging'
        stacked_on = 'base'
        stacked_type = 'nested'
        arguments = [
            (['--stop'],
                dict(help='Stop debug', action='store_true')),
            (['--start'],
                dict(help='Start debug', action='store_true')),
            (['--nginx'],
                dict(help='Debug Nginx', action='store_true')),
            (['--php'],
                dict(help='Debug PHP', action='store_true')),
            (['--fpm'],
                dict(help='Debug FastCGI', action='store_true')),
            (['--mysql'],
                dict(help='Debug MySQL', action='store_true')),
            (['--wp'],
                dict(help='Debug WordPress sites', action='store_true')),
            (['--rewrite'],
                dict(help='Debug Nginx rewrite rules', action='store_true')),
            (['-i', '--interactive'],
                dict(help='Interactive debug', action='store_true')),
            (['--import-slow-log-interval'],
                dict(help='Import MySQL slow log to Anemometer',
                     action='store', dest='interval')),
            (['site_name'],
                dict(help='Website Name', nargs='?', default=None))
            ]

    @expose(hide=True)
    def debug_nginx(self):
        """Start/Stop Nginx debug"""
        # start global debug
        if self.start and not self.app.pargs.site_name:
            try:
                debug_address = (self.app.config.get('stack', 'ip-address')
                                 .split())
            except Exception as e:
                debug_address = ['0.0.0.0/0']
            for ip_addr in debug_address:
                if not ("debug_connection "+ip_addr in open('/etc/nginx/'
                   'nginx.conf', encoding='utf-8').read()):
                    Log.info(self, "Setting up Nginx debug connection"
                             " for "+ip_addr)
                    EEShellExec.cmd_exec(self, "sed -i \"/events {{/a\\ \\ \\ "
                                               "\\ $(echo debug_connection "
                                               "{ip}\;)\" /etc/nginx/"
                                               "nginx.conf".format(ip=ip_addr))
                    self.trigger_nginx = True

            if not self.trigger_nginx:
                Log.info(self, "Nginx debug connection already enabled")

            self.msg = self.msg + ["/var/log/nginx/*.error.log"]

        # stop global debug
        elif not self.start and not self.app.pargs.site_name:
            if "debug_connection " in open('/etc/nginx/nginx.conf',
                                           encoding='utf-8').read():
                Log.info(self, "Disabling Nginx debug connections")
                EEShellExec.cmd_exec(self, "sed -i \"/debug_connection.*/d\""
                                     " /etc/nginx/nginx.conf")
                self.trigger_nginx = True
            else:
                Log.info(self, "Nginx debug connection already disabled")

        # start site specific debug
        elif self.start and self.app.pargs.site_name:
            config_path = ("/etc/nginx/sites-available/{0}"
                           .format(self.app.pargs.site_name))
            if os.path.isfile(config_path):
                if not EEShellExec.cmd_exec(self, "grep \"error.log debug\" "
                                            "{0}".format(config_path)):
                    Log.info(self, "Starting NGINX debug connection for "
                             "{0}".format(self.app.pargs.site_name))
                    EEShellExec.cmd_exec(self, "sed -i \"s/error.log;/"
                                         "error.log "
                                         "debug;/\" {0}".format(config_path))
                    self.trigger_nginx = True

                else:
                    Log.info(self, "Nginx debug for site already enabled")

                self.msg = self.msg + ['{0}{1}/logs/error.log'
                                       .format(EEVariables.ee_webroot,
                                               self.app.pargs.site_name)]

            else:
                Log.info(self, "{0} domain not valid"
                         .format(self.app.pargs.site_name))

        # stop site specific debug
        elif not self.start and self.app.pargs.site_name:
            config_path = ("/etc/nginx/sites-available/{0}"
                           .format(self.app.pargs.site_name))
            if os.path.isfile(config_path):
                if EEShellExec.cmd_exec(self, "grep \"error.log debug\" {0}"
                                        .format(config_path)):
                    Log.info(self, "Stoping NGINX debug connection for {0}"
                             .format(self.app.pargs.site_name))
                    EEShellExec.cmd_exec(self, "sed -i \"s/error.log debug;/"
                                         "error.log;/\" {0}"
                                         .format(config_path))
                    self.trigger_nginx = True

                else:

                    Log.info(self, "Nginx debug for site already disabled")
            else:
                Log.info(self, "{0} domain not valid"
                         .format(self.app.pargs.site_name))

    @expose(hide=True)
    def debug_php(self):
        """Start/Stop PHP debug"""
        # PHP global debug start
        if self.start:
            if not (EEShellExec.cmd_exec(self, "sed -n \"/upstream php"
                                               "{/,/}/p \" /etc/nginx/"
                                               "conf.d/upstream.conf "
                                               "| grep 9001")):
                Log.info(self, "Enabling PHP debug")
                data = dict(php="9001", debug="9001")
                Log.info(self, 'Writting the Nginx debug configration to file '
                         '/etc/nginx/conf.d/upstream.conf ')
                ee_nginx = open('/etc/nginx/conf.d/upstream.conf',
                                encoding='utf-8', mode='w')
                self.app.render((data), 'upstream.mustache', out=ee_nginx)
                ee_nginx.close()
                self.trigger_php = True
                self.trigger_nginx = True
            else:
                Log.info(self, "PHP debug is already enabled")

            self.msg = self.msg + ['/var/log/php5/slow.log']

        # PHP global debug stop
        else:
            if EEShellExec.cmd_exec(self, " sed -n \"/upstream php {/,/}/p\" "
                                          "/etc/nginx/conf.d/upstream.conf "
                                          "| grep 9001"):
                Log.info(self, "Disabling PHP debug")
                data = dict(php="9000", debug="9001")
                Log.debug(self, 'Writting the Nginx debug configration to file'
                          ' /etc/nginx/conf.d/upstream.conf ')
                ee_nginx = open('/etc/nginx/conf.d/upstream.conf',
                                encoding='utf-8', mode='w')
                self.app.render((data), 'upstream.mustache', out=ee_nginx)
                ee_nginx.close()
                self.trigger_php = True
                self.trigger_nginx = True
            else:
                Log.info(self, "PHP debug is already disabled")

    @expose(hide=True)
    def debug_fpm(self):
        """Start/Stop PHP5-FPM debug"""
        # PHP5-FPM start global debug
        if self.start:
            if not EEShellExec.cmd_exec(self, "grep \"log_level = debug\" "
                                              "/etc/php5/fpm/php-fpm.conf"):
                Log.info(self, "Setting up PHP5-FPM log_level = debug")
                config = configparser.ConfigParser()
                config.read('/etc/php5/fpm/php-fpm.conf')
                config.remove_option('global', 'include')
                config['global']['log_level'] = 'debug'
                config['global']['include'] = '/etc/php5/fpm/pool.d/*.conf'
                with open('/etc/php5/fpm/php-fpm.conf',
                          encoding='utf-8', mode='w') as configfile:
                    Log.debug(self, "Writting php5-FPM configuration into "
                              "/etc/php5/fpm/php-fpm.conf")
                    config.write(configfile)
                self.trigger_php = True
            else:
                Log.info(self, "PHP5-FPM log_level = debug already setup")

            self.msg = self.msg + ['/var/log/php5/fpm.log']

        # PHP5-FPM stop global debug
        else:
            if EEShellExec.cmd_exec(self, "grep \"log_level = debug\" "
                                          "/etc/php5/fpm/php-fpm.conf"):
                Log.info(self, "Disabling PHP5-FPM log_level = debug")
                config = configparser.ConfigParser()
                config.read('/etc/php5/fpm/php-fpm.conf')
                config.remove_option('global', 'include')
                config['global']['log_level'] = 'notice'
                config['global']['include'] = '/etc/php5/fpm/pool.d/*.conf'
                with open('/etc/php5/fpm/php-fpm.conf',
                          encoding='utf-8', mode='w') as configfile:
                    Log.debug(self, "writting php5 configuration into "
                              "/etc/php5/fpm/php-fpm.conf")
                    config.write(configfile)

                self.trigger_php = True
            else:
                Log.info(self, "PHP5-FPM log_level = debug  already disabled")

    @expose(hide=True)
    def debug_mysql(self):
        """Start/Stop MySQL debug"""
        # MySQL start global debug
        if self.start:
            if not EEShellExec.cmd_exec(self, "mysql -e \"show variables like"
                                              " \'slow_query_log\';\" | "
                                              "grep ON"):
                Log.info(self, "Setting up MySQL slow log")
                EEMysql.execute(self, "set global slow_query_log = "
                                      "\'ON\';")
                EEMysql.execute(self, "set global slow_query_log_file = "
                                      "\'/var/log/mysql/mysql-slow.log\';")
                EEMysql.execute(self, "set global long_query_time = 2;")
                EEMysql.execute(self, "set global log_queries_not_using"
                                      "_indexes = \'ON\';")
                if self.app.pargs.interval:
                    try:
                        cron_time = int(self.app.pargs.interval)
                    except Exception as e:
                        cron_time = 5

                    EEShellExec.cmd_exec(self, "/bin/bash -c \"crontab -l "
                                         "2> /dev/null | {{ cat; echo -e"
                                         " \\\"#EasyEngine start MySQL "
                                         "slow log \\n*/{0} * * * * "
                                         "/usr/local/bin/ee "
                                         "import-slow-log\\n"
                                         "#EasyEngine end MySQL slow log"
                                         "\\\"; }} | crontab -\""
                                         .format(cron_time))
            else:
                Log.info(self, "MySQL slow log is already enabled")

            self.msg = self.msg + ['/var/log/mysql/mysql-slow.log']

        # MySQL stop global debug
        else:
            if EEShellExec.cmd_exec(self, "mysql -e \"show variables like \'"
                                    "slow_query_log\';\" | grep ON"):
                Log.info(self, "Disabling MySQL slow log")
                EEMysql.execute(self, "set global slow_query_log = \'OFF\';")
                EEMysql.execute(self, "set global slow_query_log_file = \'"
                                "/var/log/mysql/mysql-slow.log\';")
                EEMysql.execute(self, "set global long_query_time = 10;")
                EEMysql.execute(self, "set global log_queries_not_using_index"
                                "es = \'OFF\';")
                EEShellExec.cmd_exec(self, "crontab -l | sed \'/#EasyEngine "
                                     "start/,/#EasyEngine end/d\' | crontab -")
            else:
                Log.info(self, "MySQL slow log already disabled")

    @expose(hide=True)
    def debug_wp(self):
        """Start/Stop WordPress debug"""
        if self.start and self.app.pargs.site_name:
            wp_config = ("{0}/{1}/wp-config.php"
                         .format(EEVariables.ee_webroot,
                                 self.app.pargs.site_name))
            webroot = "{0}{1}".format(EEVariables.ee_webroot,
                                      self.app.pargs.site_name)
            if os.path.isfile(wp_config):
                if not EEShellExec.cmd_exec(self, "grep \"\'WP_DEBUG\'\" {0} |"
                                            " grep true".format(wp_config)):
                    Log.info(self, "Starting WordPress debug")
                    open("{0}/htdocs/wp-content/debug.log".format(webroot),
                         encoding='utf-8', mode='a').close()
                    EEShellExec.cmd_exec(self, "chown {1}: {0}/htdocs/wp-"
                                         "content/debug.log"
                                         "".format(webroot,
                                                   EEVariables.ee_php_user))
                    EEShellExec.cmd_exec(self, "sed -i \"s/define(\'WP_DEBUG\'"
                                         ".*/define(\'WP_DEBUG\', true);\\n"
                                         "define(\'WP_DEBUG_DISPLAY\', false);"
                                         "\\ndefine(\'WP_DEBUG_LOG\', true);"
                                         "\\ndefine(\'SAVEQUERIES\', true);/\""
                                         " {0}".format(wp_config))
                    EEShellExec.cmd_exec(self, "cd {0}/htdocs/ && wp"
                                         " plugin --allow-root install "
                                         "developer query-monitor"
                                         .format(webroot))
                    EEShellExec.cmd_exec(self, "chown -R {1}: {0}/htdocs/"
                                         "wp-content/plugins"
                                         .format(webroot,
                                                 EEVariables.ee_php_user))

                self.msg = self.msg + ['{0}{1}/htdocs/wp-content'
                                       '/debug.log'
                                       .format(EEVariables.ee_webroot,
                                               self.app.pargs.site_name)]

            else:
                Log.info(self, "{0} domain not valid"
                         .format(self.app.pargs.site_name))

        elif not self.start and self.app.pargs.site_name:
            wp_config = ("{0}{1}/wp-config.php"
                         .format(EEVariables.ee_webroot,
                                 self.app.pargs.site_name))
            webroot = "{0}{1}".format(EEVariables.ee_webroot,
                                      self.app.pargs.site_name)
            if os.path.isfile(wp_config):
                if EEShellExec.cmd_exec(self, "grep \"\'WP_DEBUG\'\" {0} | "
                                        "grep true".format(wp_config)):
                    Log.info(self, "Disabling WordPress debug")
                    EEShellExec.cmd_exec(self, "sed -i \"s/define(\'WP_DEBUG\'"
                                         ", true);/define(\'WP_DEBUG\', "
                                         "false);/\" {0}".format(wp_config))
                    EEShellExec.cmd_exec(self, "sed -i \"/define(\'"
                                         "WP_DEBUG_DISPLAY\', false);/d\" {0}"
                                         .format(wp_config))
                    EEShellExec.cmd_exec(self, "sed -i \"/define(\'"
                                         "WP_DEBUG_LOG\', true);/d\" {0}"
                                         .format(wp_config))
                    EEShellExec.cmd_exec(self, "sed -i \"/define(\'"
                                         "SAVEQUERIES\', "
                                         "true);/d\" {0}".format(wp_config))
                else:
                    Log.info(self, "WordPress debug all already disabled")
        else:
            Log.error(self, "Missing argument site name")

    @expose(hide=True)
    def debug_rewrite(self):
        """Start/Stop Nginx rewrite rules debug"""
        # Start Nginx rewrite debug globally
        if self.start and not self.app.pargs.site_name:
            if not EEShellExec.cmd_exec(self, "grep \"rewrite_log on;\" "
                                        "/etc/nginx/nginx.conf"):
                Log.info(self, "Setting up Nginx rewrite logs")
                EEShellExec.cmd_exec(self, "sed -i \'/http {/a \\\\t"
                                     "rewrite_log on;\' /etc/nginx/nginx.conf")
                self.trigger_nginx = True
            else:
                Log.info(self, "Nginx rewrite logs already enabled")

            if '/var/log/nginx/*.error.log' not in self.msg:
                self.msg = self.msg + ['/var/log/nginx/*.error.log']

        # Stop Nginx rewrite debug globally
        elif not self.start and not self.app.pargs.site_name:
            if EEShellExec.cmd_exec(self, "grep \"rewrite_log on;\" "
                                    "/etc/nginx/nginx.conf"):
                Log.info(self, "Disabling Nginx rewrite logs")
                EEShellExec.cmd_exec(self, "sed -i \"/rewrite_log.*/d\""
                                     " /etc/nginx/nginx.conf")
                self.trigger_nginx = True
            else:
                Log.info(self, "Nginx rewrite logs already disabled")
        # Start Nginx rewrite for site
        elif self.start and self.app.pargs.site_name:
            config_path = ("/etc/nginx/sites-available/{0}"
                           .format(self.app.pargs.site_name))
            if not EEShellExec.cmd_exec(self, "grep \"rewrite_log on;\" {0}"
                                        .format(config_path)):
                Log.info(self, "Setting up Nginx rewrite logs for {0}"
                         .format(self.app.pargs.site_name))
                EEShellExec.cmd_exec(self, "sed -i \"/access_log/i \\\\\\t"
                                     "rewrite_log on;\" {0}"
                                     .format(config_path))
                self.trigger_nginx = True
            else:
                Log.info(self, "Nginx rewrite logs for {0} already setup"
                         .format(self.app.pargs.site_name))

            if ('{0}{1}/logs/error.log'.format(EEVariables.ee_webroot,
                                               self.app.pargs.site_name)
               not in self.msg):
                self.msg = self.msg + ['{0}{1}/logs/error.log'
                                       .format(EEVariables.ee_webroot,
                                               self.app.pargs.site_name)]

        # Stop Nginx rewrite for site
        elif not self.start and self.app.pargs.site_name:
            config_path = ("/etc/nginx/sites-available/{0}"
                           .format(self.app.pargs.site_name))
            if EEShellExec.cmd_exec(self, "grep \"rewrite_log on;\" {0}"
                                    .format(config_path)):
                Log.info(self, "Disabling Nginx rewrite logs for {0}"
                         .format(self.app.pargs.site_name))
                EEShellExec.cmd_exec(self, "sed -i \"/rewrite_log.*/d\" {0}"
                                     .format(config_path))
                self.trigger_nginx = True
            else:
                Log.info(self, "Nginx rewrite logs for {0} already "
                         " disabled".format(self.app.pargs.site_name))

    @expose(hide=True)
    def signal_handler(self, signal, frame):
        """Handle Ctrl+c hevent for -i option of debug"""
        self.start = False
        if self.app.pargs.nginx:
            self.debug_nginx()
        if self.app.pargs.php:
            self.debug_php()
        if self.app.pargs.fpm:
            self.debug_fpm()
        if self.app.pargs.mysql:
            # MySQL debug will not work for remote MySQL
            if EEVariables.ee_mysql_host is "localhost":
                self.debug_mysql()
            else:
                Log.warn(self, "Remote MySQL found, EasyEngine will not "
                         "enable remote debug")
        if self.app.pargs.wp:
            self.debug_wp()
        if self.app.pargs.rewrite:
            self.debug_rewrite()

        # Reload Nginx
        if self.trigger_nginx:
            EEService.reload_service(self, 'nginx')

        # Reload PHP
        if self.trigger_php:
            EEService.reload_service(self, 'php5-fpm')
        self.app.close(0)

    @expose(hide=True)
    def default(self):
        """Default function of debug"""
        self.start = True
        self.interactive = False
        self.msg = []
        self.trigger_nginx = False
        self.trigger_php = False

        if self.app.pargs.stop:
            self.start = False

        if ((not self.app.pargs.nginx) and (not self.app.pargs.php)
           and (not self.app.pargs.fpm) and (not self.app.pargs.mysql)
           and (not self.app.pargs.wp) and (not self.app.pargs.rewrite)
           and (not self.app.pargs.site_name)):
            self.app.pargs.nginx = True
            self.app.pargs.php = True
            self.app.pargs.fpm = True
            self.app.pargs.mysql = True
            self.app.pargs.rewrite = True

        if ((not self.app.pargs.nginx) and (not self.app.pargs.php)
           and (not self.app.pargs.fpm) and (not self.app.pargs.mysql)
           and (not self.app.pargs.wp) and (not self.app.pargs.rewrite)
           and self.app.pargs.site_name):
            self.app.pargs.nginx = True
            self.app.pargs.wp = True
            self.app.pargs.rewrite = True

        if self.app.pargs.nginx:
            self.debug_nginx()
        if self.app.pargs.php:
            self.debug_php()
        if self.app.pargs.fpm:
            self.debug_fpm()
        if self.app.pargs.mysql:
            # MySQL debug will not work for remote MySQL
            if EEVariables.ee_mysql_host is "localhost":
                self.debug_mysql()
            else:
                Log.warn(self, "Remote MySQL found, EasyEngine will not "
                         "enable remote debug")
        if self.app.pargs.wp:
            self.debug_wp()
        if self.app.pargs.rewrite:
            self.debug_rewrite()

        if self.app.pargs.interactive:
            self.interactive = True

        # Reload Nginx
        if self.trigger_nginx:
            EEService.reload_service(self, 'nginx')
        # Reload PHP
        if self.trigger_php:
            EEService.reload_service(self, 'php5-fpm')

        if len(self.msg) > 0:
            if not self.app.pargs.interactive:
                disp_msg = ' '.join(self.msg)
                Log.info(self, "Use following command to check debug logs:\n"
                         + Log.ENDC + "tail -f {0}".format(disp_msg))
            else:
                signal.signal(signal.SIGINT, self.signal_handler)
                watch_list = []
                for w_list in self.msg:
                    watch_list = watch_list + glob.glob(w_list)

                logwatch(self, watch_list)


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EEDebugController)
    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', debug_plugin_hook)
