"""Debug Plugin for EasyEngine."""

from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.shellexec import EEShellExec
from ee.core.mysql import EEMysql


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
            ]

    @expose(hide=True)
    def debug_nginx(self):
        self.trigger_nginx = False
        if self.start:
            try:
                debug_address = (self.app.config.get('stack', 'ip-address')
                                 .split())
            except Exception as e:
                debug_address = ['0.0.0.0/0']
            for ip_addr in debug_address:
                if not ("debug_connection "+ip_addr in open('/etc/nginx/'
                   'nginx.conf').read()):
                    print("Setting up NGINX debug connection for "+ip_addr)
                    EEShellExec.cmd_exec(self, "sed -i \"/events {{/a\\ \\ \\ "
                                               "\\ $(echo debug_connection "
                                               "{ip}\;)\" /etc/nginx/"
                                               "nginx.conf".format(ip=ip_addr))
                    self.trigger_nginx = True

            if not self.trigger_nginx:
                print("NGINX debug connection already enabled")

            self.msg = self.msg + " /var/log/nginx/*.error.log"

        else:
            if "debug_connection " in open('/etc/nginx/nginx.conf').read():
                print("Disabling Nginx debug connections")
                EEShellExec.cmd_exec(self, "sed -i \"/debug_connection.*/d\""
                                     " /etc/nginx/nginx.conf")
                self.trigger_nginx = True
            else:
                print("Nginx debug connection already disbaled")

    @expose(hide=True)
    def debug_php(self):
        if self.start:
            if not (EEShellExec.cmd_exec(self, "sed -n \"/upstream php"
                                               "{/,/}/p \" /etc/nginx/"
                                               "conf.d/upstream.conf "
                                               "| grep 9001")):
                print("Enabling PHP debug")
                data = dict(php="9001", debug="9001")
                self.app.log.debug('writting the nginx configration to file'
                                   '/etc/nginx/conf.d/upstream.conf ')
                ee_nginx = open('/etc/nginx/conf.d/upstream.conf', 'w')
                self.app.render((data), 'upstream.mustache', out=ee_nginx)
                ee_nginx.close()
                self.trigger_php = True
            else:
                print("PHP debug is allready enabled")
        else:
            if EEShellExec.cmd_exec(self, "sed -n \"/upstream php {/,/}/p\" "
                                          "/etc/nginx/conf.d/upstream.conf "
                                          "| grep 9001"):
                print("Disabling PHP debug")
                data = dict(php="9000", debug="9001")
                self.app.log.debug('writting the nginx configration to file'
                                   '/etc/nginx/conf.d/upstream.conf ')
                ee_nginx = open('/etc/nginx/conf.d/upstream.conf', 'w')
                self.app.render((data), 'upstream.mustache', out=ee_nginx)
                ee_nginx.close()
                self.trigger_php = True
            else:
                print("PHP debug is allready disbaled")

    @expose(hide=True)
    def debug_fpm(self):
        if self.start:
            if not EEShellExec.cmd_exec(self, "grep \"log_level = debug\" "
                                              "/etc/php5/fpm/php-fpm.conf"):
                print("Setting up PHP5-FPM log_level = debug")
                EEShellExec.cmd_exec(self, "sed -i \"s\';log_level.*\'log_"
                                           "level = debug\'\" /etc/php5/fpm"
                                           "/php-fpm.conf")
                self.trigger_php = True
            else:
                print("PHP5-FPM log_level = debug already setup")
        else:
            if EEShellExec.cmd_exec(self, "grep \"log_level = debug\" "
                                          "/etc/php5/fpm/php-fpm.conf"):
                print("Disabling PHP5-FPM log_level = debug")
                EEShellExec.cmd_exec(self, "sed -i \"s\'log_level.*\';log_"
                                           "level = notice\'\" /etc/php5/fpm"
                                           "/php-fpm.conf")
                self.trigger_php = True
            else:
                print("PHP5-FPM log_level = debug already disabled")

    @expose(hide=True)
    def debug_mysql(self):
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
                print("MySQL slow log is allready enabled")
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
                print("MySQL slow log already disabled")

    @expose(hide=True)
    def debug_wp(self):
        if self.start:
            print("Start WP debug")
        else:
            print("Stop WP debug")

    @expose(hide=True)
    def debug_rewrite(self):
        if self.start:
            print("Start WP-Rewrite debug")
        else:
            print("Stop WP-Rewrite debug")

    @expose(hide=True)
    def default(self):
        self.start = True
        self.interactive = False
        self.msg = ""

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


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EEDebugController)
    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', debug_plugin_hook)
