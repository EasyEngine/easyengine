"""Debug Plugin for EasyEngine."""

from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.shellexec import EEShellExec
from ee.core.mysql import EEMysql
from ee.core.services import EEService
import os


def debug_plugin_hook(app):
    # do something with the ``app`` object here.
    pass


class EEDebugController(CementBaseController):
    class Meta:
        label = 'debug'
        description = 'debug command enables/disbaled stack debug'
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
        # start global debug
        if self.start and not self.app.pargs.site_name:
            try:
                debug_address = (self.app.config.get('stack', 'ip-address')
                                 .split())
            except Exception as e:
                debug_address = ['0.0.0.0/0']
            for ip_addr in debug_address:
                if not ("debug_connection "+ip_addr in open('/etc/nginx/'
                   'nginx.conf').read()):
                    self.app.log.info("Setting up NGINX debug connection"
                                      " for "+ip_addr)
                    EEShellExec.cmd_exec(self, "sed -i \"/events {{/a\\ \\ \\ "
                                               "\\ $(echo debug_connection "
                                               "{ip}\;)\" /etc/nginx/"
                                               "nginx.conf".format(ip=ip_addr))
                    self.trigger_nginx = True

            if not self.trigger_nginx:
                self.app.log.info("NGINX debug connection already enabled")

            self.msg = self.msg + [" /var/log/nginx/*.error.log"]

        # stop global debug
        elif not self.start and not self.app.pargs.site_name:
            if "debug_connection " in open('/etc/nginx/nginx.conf').read():
                self.app.log.info("Disabling Nginx debug connections")
                EEShellExec.cmd_exec(self, "sed -i \"/debug_connection.*/d\""
                                     " /etc/nginx/nginx.conf")
                self.trigger_nginx = True
            else:
                self.app.log.info("Nginx debug connection already disbaled")

        # start site specific debug
        elif self.start and self.app.pargs.site_name:
            config_path = ("/etc/nginx/sites-available/{0}"
                           .format(self.app.pargs.site_name))
            if os.path.isfile(config_path):
                if not EEShellExec.cmd_exec("grep \"error.log debug\" {0}"
                                            .format(config_path)):
                    self.app.log.info("Starting NGINX debug connection for "
                                      "{0}"
                                      .format(self.app.pargs.site_name))
                    EEShellExec.cmd_exec("sed -i \"s/error.log;/error.log "
                                         "debug;/\" {0}".format(config_path))
                    self.trigger_nginx = True

                else:
                    self.app.log.info("Debug for site allready enabled")

                self.msg = self.msg + ['/var/www//logs/error.log'
                                       .format(self.app.pargs.site_name)]

            else:
                self.app.log.info("{0} domain not valid"
                                  .format(self.app.pargs.site_name))

        # stop site specific debug
        elif not self.start and self.app.pargs.site_name:
            config_path = ("/etc/nginx/sites-available/{0}"
                           .format(self.app.pargs.site_name))
            if os.path.isfile(config_path):
                if EEShellExec.cmd_exec("grep \"error.log debug\" {0}"
                                        .format(config_path)):
                    self.app.log.info("Stoping NGINX debug connection for {0}"
                                      .format(self.app.pargs.site_name))
                    EEShellExec.cmd_exec("sed -i \"s/error.log debug;/"
                                         "error.log;/\" {0}"
                                         .format(config_path))
                    self.trigger_nginx = True

                else:
                    self.app.log.info("Debug for site allready disbaled")

            else:
                self.app.log.info("{0} domain not valid"
                                  .format(self.app.pargs.site_name))

    @expose(hide=True)
    def debug_php(self):
        # PHP global debug start
        if self.start:
            if not (EEShellExec.cmd_exec(self, "sed -n \"/upstream php"
                                               "{/,/}/p \" /etc/nginx/"
                                               "conf.d/upstream.conf "
                                               "| grep 9001")):
                self.app.log.info("Enabling PHP debug")
                data = dict(php="9001", debug="9001")
                self.app.log.info('writting the nginx configration to file'
                                  '/etc/nginx/conf.d/upstream.conf ')
                ee_nginx = open('/etc/nginx/conf.d/upstream.conf', 'w')
                self.app.render((data), 'upstream.mustache', out=ee_nginx)
                ee_nginx.close()
                self.trigger_php = True
            else:
                self.app.log.info("PHP debug is allready enabled")

            self.msg = self.msg + ['/var/log/php5/slow.log']

        # PHP global debug stop
        else:
            if EEShellExec.cmd_exec(self, "sed -n \"/upstream php {/,/}/p\" "
                                          "/etc/nginx/conf.d/upstream.conf "
                                          "| grep 9001"):
                self.app.log.info("Disabling PHP debug")
                data = dict(php="9000", debug="9001")
                self.app.log.info('writting the nginx configration to file'
                                  '/etc/nginx/conf.d/upstream.conf ')
                ee_nginx = open('/etc/nginx/conf.d/upstream.conf', 'w')
                self.app.render((data), 'upstream.mustache', out=ee_nginx)
                ee_nginx.close()
                self.trigger_php = True
            else:
                self.app.log.info("PHP debug is allready disbaled")

    @expose(hide=True)
    def debug_fpm(self):
        # PHP5-FPM start global debug
        if self.start:
            if not EEShellExec.cmd_exec(self, "grep \"log_level = debug\" "
                                              "/etc/php5/fpm/php-fpm.conf"):
                self.app.log.info("Setting up PHP5-FPM log_level = debug")
                EEShellExec.cmd_exec(self, "sed -i \"s\';log_level.*\'log_"
                                           "level = debug\'\" /etc/php5/fpm"
                                           "/php-fpm.conf")
                self.trigger_php = True
            else:
                self.app.log.info("PHP5-FPM log_level = debug already setup")

            self.msg = self.msg + ['/var/log/php5/fpm.log']
        # PHP5-FPM stop global debug
        else:
            if EEShellExec.cmd_exec(self, "grep \"log_level = debug\" "
                                          "/etc/php5/fpm/php-fpm.conf"):
                self.app.log.info("Disabling PHP5-FPM log_level = debug")
                EEShellExec.cmd_exec(self, "sed -i \"s\'log_level.*\';log_"
                                           "level = notice\'\" /etc/php5/fpm"
                                           "/php-fpm.conf")
                self.trigger_php = True
            else:
                self.app.log.info("PHP5-FPM log_level = debug "
                                  " already disabled")

    @expose(hide=True)
    def debug_mysql(self):
        # MySQL start global debug
        if self.start:
            if not EEShellExec.cmd_exec(self, "mysql -e \"show variables like"
                                              " \'slow_query_log\';\" | "
                                              "grep ON"):
                print("Setting up MySQL slow log")
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
                    EEShellExec.cmd_exec(self, "/bin/bash -c \"crontab -l 2> "
                                         "/dev/null | {{ cat; echo -e"
                                         " \\\"#EasyEngine start MySQL slow"
                                         " log \\n*/{0} * * * * "
                                         "/usr/local/sbin/ee import-slow-log\\"
                                         "n#EasyEngine end MySQL slow log\\\";"
                                         " }} | crontab -\"".format(cron_time))
            else:
                self.app.log.info("MySQL slow log is allready enabled")

            self.msg = self.msg + ['/var/log/mysql/mysql-slow.log']

        # MySQL stop global debug
        else:
            if EEShellExec.cmd_exec(self, "mysql -e \"show variables like \'"
                                    "slow_query_log\';\" | grep ON"):
                print("Disabling MySQL slow log")
                EEMysql.execute(self, "set global slow_query_log = \'OFF\';")
                EEMysql.execute(self, "set global slow_query_log_file = \'"
                                "/var/log/mysql/mysql-slow.log\';")
                EEMysql.execute(self, "set global long_query_time = 10;")
                EEMysql.execute(self, "set global log_queries_not_using_index"
                                "es = \'OFF\';")
                EEShellExec.cmd_exec(self, "crontab -l | sed \'/#EasyEngine "
                                     "start/,/#EasyEngine end/d\' | crontab -")
            else:
                self.app.log.info("MySQL slow log already disabled")

    @expose(hide=True)
    def debug_wp(self):
        if self.start and self.app.pargs.site_name:
            wp_config = ("/var/www/{0}/wp-config.php"
                         .format(self.app.pargs.site_name))
            webroot = "/var/www/{0}".format(self.app.pargs.site_name)
            if os.path.isfile(wp_config):
                if not EEShellExec.cmd_exec(self, "grep \"\'WP_DEBUG\'\" {0} |"
                                            " grep true".format(wp_config)):
                    self.app.log.info("Starting WordPress debug")
                    open("{0}/htdocs/wp-content/debug.log".format(webroot),
                         'a').close()
                    EEShellExec.cmd_exec(self, "chown www-data: {0}/htdocs/wp-"
                                         "content/debug.log".format(webroot))
                    EEShellExec.cmd_exec(self, "sed -i \"s/define(\'WP_DEBUG\'"
                                         ".*/define(\'WP_DEBUG\', true);\\n"
                                         "define(\'WP_DEBUG_DISPLAY\', false);"
                                         "\\ndefine(\'WP_DEBUG_LOG\', true);"
                                         "\\ndefine(\'SAVEQUERIES\', true);/\""
                                         " {0}".format(wp_config))
                    EEShellExec.cmd_exec(self, "cd {0}/htdocs/ && wp"
                                         " plugin --allow-root install "
                                         "developer".format(webroot))
                    EEShellExec.cmd_exec(self, "chown -R www-data: {0}/htdocs/"
                                         "wp-content/plugins"
                                         .format(webroot))
                else:
                    self.app.log.info("WordPress debug log already enabled")
            else:
                self.app.log.info("{0} domain not valid"
                                  .format(self.app.pargs.site_name))

        elif not self.start and self.app.pargs.site_name:
            wp_config = ("/var/www/{0}/wp-config.php"
                         .format(self.app.pargs.site_name))
            webroot = "/var/www/{0}".format(self.app.pargs.site_name)
            if os.path.isfile(wp_config):
                if EEShellExec.cmd_exec(self, "grep \"\'WP_DEBUG\'\" {0} | "
                                        "grep true".format(wp_config)):
                    self.app.log.info("Disabling WordPress debug")
                    EEShellExec.cmd_exec(self, "sed -i \"s/define(\'WP_DEBUG\'"
                                         ", true);/define(\'WP_DEBUG\', "
                                         "false);/\" {0}".format(wp_config))
                    EEShellExec.cmd_exec(self, "sed -i \"/define(\'"
                                         "WP_DEBUG_DISPLAY\', false);/d\" {0}"
                                         .format(wp_config))
                    EEShellExec.cmd_exec(self, "sed -i \"/define(\'"
                                         "WP_DEBUG_LOG\', true);/d\" {0}"
                                         .format(wp_config))
                    EEShellExec.cmd_exec("sed -i \"/define(\'"
                                         "SAVEQUERIES\', "
                                         "true);/d\" {0}".format(wp_config))
                else:
                    print("WordPress debug all already disbaled")
            else:
                self.app.log.info("{0} domain not valid"
                                  .format(self.app.pargs.site_name))
        else:
            self.app.log.info("Missing argument site_name")

    @expose(hide=True)
    def debug_rewrite(self):
        # Start Nginx rewrite debug globally
        if self.start and not self.app.pargs.site_name:
            if not EEShellExec.cmd_exec(self, "grep \"rewrite_log on;\" "
                                        "/etc/nginx/nginx.conf"):
                self.app.log.info("Setting up Nginx rewrite logs")
                EEShellExec.cmd_exec(self, "sed -i \'/http {/a \\\\t"
                                     "rewrite_log on;\' /etc/nginx/nginx.conf")
                self.trigger_nginx = True
            else:
                self.app.log.info("NGINX rewrite logs already enabled")

            if '/var/log/nginx/*.error.log' not in self.msg:
                self.msg = self.msg + ['/var/log/nginx/*.error.log']

        # Stop Nginx rewrite debug globally
        elif not self.start and not self.app.pargs.site_name:
            if EEShellExec.cmd_exec(self, "grep \"rewrite_log on;\" "
                                    "/etc/nginx/nginx.conf"):
                self.app.log.info("Disabling Nginx rewrite logs")
                EEShellExec.cmd_exec(self, "sed -i \"/rewrite_log.*/d\""
                                     " /etc/nginx/nginx.conf")
                self.trigger_nginx = True
            else:
                self.app.log.info("NGINX rewrite logs already disbaled")
        # Start Nginx rewrite for site
        elif self.start and self.app.pargs.site_name:
            config_path = ("/etc/nginx/sites-available/{0}.conf"
                           .format(self.app.pargs.site_name))
            if not EEShellExec.cmd_exec(self, "grep \"rewrite_log on;\" {0}"
                                        .format(config_path)):
                self.app.log.info("Setting up NGINX rewrite logs for {0}"
                                  .format(self.app.pargs.site_name))
                EEShellExec.cmd_exec(self, "sed -i \"/access_log/i \\\\\\t"
                                     "rewrite_log on;\" {0}"
                                     .format(config_path))
                self.trigger_nginx = True
            else:
                self.app.log.info("Nginx rewrite logs for {0} allready setup"
                                  .format(self.app.pargs.site_name))

            if ('/var/www/{0}/logs/error.log'.format(self.app.pargs.site_name)
               not in self.msg):
                self.msg = self.msg + ['/var/www/{0}/logs/error.log'
                                       .format(self.app.pargs.site_name)]

        # Stop Nginx rewrite for site
        elif not self.start and self.app.pargs.site_name:
            config_path = ("/etc/nginx/sites-available/{0}.conf"
                           .format(self.app.pargs.site_name))
            if EEShellExec.cmd_exec(self, "grep \"rewrite_log on;\" {0}"
                                    .format(config_path)):
                self.app.log.info("Disabling NGINX rewrite logs for {0}"
                                  .format(self.app.pargs.site_name))
                EEShellExec.cmd_exec(self, "sed -i \"/rewrite_log.*/d\" {0}"
                                     .format(config_path))
                self.trigger_nginx = True
            else:
                self.app.log.info("Nginx rewrite logs for {0} allready "
                                  " disbaled"
                                  .format(self.app.pargs.site_name))

    @expose(hide=True)
    def default(self):
        self.start = True
        self.interactive = False
        self.msg = []
        self.trigger_nginx = False
        self.trigger_php = False

        if self.app.pargs.stop:
            self.start = False

        if ((not self.app.pargs.nginx) and (not self.app.pargs.php)
           and (not self.app.pargs.fpm) and (not self.app.pargs.mysql)
           and (not self.app.pargs.wp) and (not self.app.pargs.rewrite)):
            self.debug_nginx()
            self.debug_php()
            self.debug_fpm()
            self.debug_mysql()
            self.debug_wp()
            self.debug_rewrite()

        if self.app.pargs.nginx:
            self.debug_nginx()
        if self.app.pargs.php:
            self.debug_php()
        if self.app.pargs.fpm:
            self.debug_fpm()
        if self.app.pargs.mysql:
            self.debug_mysql()
        if self.app.pargs.wp:
            self.debug_wp()
        if self.app.pargs.rewrite:
            self.debug_rewrite()

        if self.app.pargs.interactive:
            self.interactive = True

        # Reload Nginx
        if self.trigger_nginx:
            EEService.reload_service(self, ['nginx'])
        # Reload PHP
        if self.trigger_php:
            EEService.reload_service(self, ['php5-fpm'])

        if len(self.msg) > 0:
            self.app.log.info("Use following command to check debug logs:"
                              "\n{0}".format(self.msg.join()))


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EEDebugController)
    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', debug_plugin_hook)
