"""EEInfo Plugin for EasyEngine."""

from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.variables import EEVariables
from pynginxconfig import NginxConfig
from ee.core.aptget import EEAptGet
from ee.core.shellexec import EEShellExec
from ee.core.logging import Log
import os
import configparser


def ee_info_hook(app):
    # do something with the ``app`` object here.
    pass


class EEInfoController(CementBaseController):
    class Meta:
        label = 'info'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = ('Display configuration information related to Nginx,'
                       ' PHP and MySQL')
        arguments = [
            (['--mysql'],
                dict(help='Get MySQL configuration information',
                     action='store_true')),
            (['--php'],
                dict(help='Get PHP configuration information',
                     action='store_true')),
            (['--php7'],
                dict(help='Get PHP 7.0 configuration information',
                     action='store_true')),
            (['--nginx'],
                dict(help='Get Nginx configuration information',
                     action='store_true')),
            ]
        usage = "ee info [options]"

    @expose(hide=True)
    def info_nginx(self):
        """Display Nginx information"""
        version = os.popen("nginx -v 2>&1 | cut -d':' -f2 | cut -d' ' -f2 | "
                           "cut -d'/' -f2 | tr -d '\n'").read()
        allow = os.popen("grep ^allow /etc/nginx/common/acl.conf | "
                         "cut -d' ' -f2 | cut -d';' -f1 | tr '\n' ' '").read()
        nc = NginxConfig()
        nc.loadf('/etc/nginx/nginx.conf')
        user = nc.get('user')[1]
        worker_processes = nc.get('worker_processes')[1]
        worker_connections = nc.get([('events',), 'worker_connections'])[1]
        keepalive_timeout = nc.get([('http',), 'keepalive_timeout'])[1]
        fastcgi_read_timeout = nc.get([('http',),
                                       'fastcgi_read_timeout'])[1]
        client_max_body_size = nc.get([('http',),
                                       'client_max_body_size'])[1]
        data = dict(version=version, allow=allow, user=user,
                    worker_processes=worker_processes,
                    keepalive_timeout=keepalive_timeout,
                    worker_connections=worker_connections,
                    fastcgi_read_timeout=fastcgi_read_timeout,
                    client_max_body_size=client_max_body_size)
        self.app.render((data), 'info_nginx.mustache')

    @expose(hide=True)
    def info_php(self):
        """Display PHP information"""
        version = os.popen("{0} -v 2>/dev/null | head -n1 | cut -d' ' -f2 |".format("php5.6" if (EEVariables.ee_platform_codename == 'trusty' or EEVariables.ee_platform_codename == 'xenial') else "php") +
                           " cut -d'+' -f1 | tr -d '\n'").read
        config = configparser.ConfigParser()
        config.read('/etc/{0}/fpm/php.ini'.format("php/5.6" if (EEVariables.ee_platform_codename == 'trusty' or EEVariables.ee_platform_codename == 'xenial') else "php5"))
        expose_php = config['PHP']['expose_php']
        memory_limit = config['PHP']['memory_limit']
        post_max_size = config['PHP']['post_max_size']
        upload_max_filesize = config['PHP']['upload_max_filesize']
        max_execution_time = config['PHP']['max_execution_time']

        config.read('/etc/{0}/fpm/pool.d/www.conf'.format("php/5.6" if (EEVariables.ee_platform_codename == 'trusty' or EEVariables.ee_platform_codename == 'xenial') else "php5"))
        www_listen = config['www']['listen']
        www_ping_path = config['www']['ping.path']
        www_pm_status_path = config['www']['pm.status_path']
        www_pm = config['www']['pm']
        www_pm_max_requests = config['www']['pm.max_requests']
        www_pm_max_children = config['www']['pm.max_children']
        www_pm_start_servers = config['www']['pm.start_servers']
        www_pm_min_spare_servers = config['www']['pm.min_spare_servers']
        www_pm_max_spare_servers = config['www']['pm.max_spare_servers']
        www_request_terminate_time = (config['www']
                                            ['request_terminate_timeout'])
        try:
            www_xdebug = (config['www']['php_admin_flag[xdebug.profiler_enable'
                                        '_trigger]'])
        except Exception as e:
            www_xdebug = 'off'

        config.read('/etc/{0}/fpm/pool.d/debug.conf'.format("php/5.6" if (EEVariables.ee_platform_codename == 'trusty' or EEVariables.ee_platform_codename == 'xenial') else "php5"))
        debug_listen = config['debug']['listen']
        debug_ping_path = config['debug']['ping.path']
        debug_pm_status_path = config['debug']['pm.status_path']
        debug_pm = config['debug']['pm']
        debug_pm_max_requests = config['debug']['pm.max_requests']
        debug_pm_max_children = config['debug']['pm.max_children']
        debug_pm_start_servers = config['debug']['pm.start_servers']
        debug_pm_min_spare_servers = config['debug']['pm.min_spare_servers']
        debug_pm_max_spare_servers = config['debug']['pm.max_spare_servers']
        debug_request_terminate = (config['debug']
                                         ['request_terminate_timeout'])
        try:
            debug_xdebug = (config['debug']['php_admin_flag[xdebug.profiler_'
                                            'enable_trigger]'])
        except Exception as e:
            debug_xdebug = 'off'

        data = dict(version=version, expose_php=expose_php,
                    memory_limit=memory_limit, post_max_size=post_max_size,
                    upload_max_filesize=upload_max_filesize,
                    max_execution_time=max_execution_time,
                    www_listen=www_listen, www_ping_path=www_ping_path,
                    www_pm_status_path=www_pm_status_path, www_pm=www_pm,
                    www_pm_max_requests=www_pm_max_requests,
                    www_pm_max_children=www_pm_max_children,
                    www_pm_start_servers=www_pm_start_servers,
                    www_pm_min_spare_servers=www_pm_min_spare_servers,
                    www_pm_max_spare_servers=www_pm_max_spare_servers,
                    www_request_terminate_timeout=www_request_terminate_time,
                    www_xdebug_profiler_enable_trigger=www_xdebug,
                    debug_listen=debug_listen, debug_ping_path=debug_ping_path,
                    debug_pm_status_path=debug_pm_status_path,
                    debug_pm=debug_pm,
                    debug_pm_max_requests=debug_pm_max_requests,
                    debug_pm_max_children=debug_pm_max_children,
                    debug_pm_start_servers=debug_pm_start_servers,
                    debug_pm_min_spare_servers=debug_pm_min_spare_servers,
                    debug_pm_max_spare_servers=debug_pm_max_spare_servers,
                    debug_request_terminate_timeout=debug_request_terminate,
                    debug_xdebug_profiler_enable_trigger=debug_xdebug)
        self.app.render((data), 'info_php.mustache')

    @expose(hide=True)
    def info_php7(self):
        """Display PHP information"""
        version = os.popen("php7.0 -v 2>/dev/null | head -n1 | cut -d' ' -f2 |"
                           " cut -d'+' -f1 | tr -d '\n'").read
        config = configparser.ConfigParser()
        config.read('/etc/php/7.0/fpm/php.ini')
        expose_php = config['PHP']['expose_php']
        memory_limit = config['PHP']['memory_limit']
        post_max_size = config['PHP']['post_max_size']
        upload_max_filesize = config['PHP']['upload_max_filesize']
        max_execution_time = config['PHP']['max_execution_time']

        config.read('/etc/php/7.0/fpm/pool.d/www.conf')
        www_listen = config['www']['listen']
        www_ping_path = config['www']['ping.path']
        www_pm_status_path = config['www']['pm.status_path']
        www_pm = config['www']['pm']
        www_pm_max_requests = config['www']['pm.max_requests']
        www_pm_max_children = config['www']['pm.max_children']
        www_pm_start_servers = config['www']['pm.start_servers']
        www_pm_min_spare_servers = config['www']['pm.min_spare_servers']
        www_pm_max_spare_servers = config['www']['pm.max_spare_servers']
        www_request_terminate_time = (config['www']
                                            ['request_terminate_timeout'])
        try:
            www_xdebug = (config['www']['php_admin_flag[xdebug.profiler_enable'
                                        '_trigger]'])
        except Exception as e:
            www_xdebug = 'off'

        config.read('/etc/php/7.0/fpm/pool.d/debug.conf')
        debug_listen = config['debug']['listen']
        debug_ping_path = config['debug']['ping.path']
        debug_pm_status_path = config['debug']['pm.status_path']
        debug_pm = config['debug']['pm']
        debug_pm_max_requests = config['debug']['pm.max_requests']
        debug_pm_max_children = config['debug']['pm.max_children']
        debug_pm_start_servers = config['debug']['pm.start_servers']
        debug_pm_min_spare_servers = config['debug']['pm.min_spare_servers']
        debug_pm_max_spare_servers = config['debug']['pm.max_spare_servers']
        debug_request_terminate = (config['debug']
                                         ['request_terminate_timeout'])
        try:
            debug_xdebug = (config['debug']['php_admin_flag[xdebug.profiler_'
                                            'enable_trigger]'])
        except Exception as e:
            debug_xdebug = 'off'

        data = dict(version=version, expose_php=expose_php,
                    memory_limit=memory_limit, post_max_size=post_max_size,
                    upload_max_filesize=upload_max_filesize,
                    max_execution_time=max_execution_time,
                    www_listen=www_listen, www_ping_path=www_ping_path,
                    www_pm_status_path=www_pm_status_path, www_pm=www_pm,
                    www_pm_max_requests=www_pm_max_requests,
                    www_pm_max_children=www_pm_max_children,
                    www_pm_start_servers=www_pm_start_servers,
                    www_pm_min_spare_servers=www_pm_min_spare_servers,
                    www_pm_max_spare_servers=www_pm_max_spare_servers,
                    www_request_terminate_timeout=www_request_terminate_time,
                    www_xdebug_profiler_enable_trigger=www_xdebug,
                    debug_listen=debug_listen, debug_ping_path=debug_ping_path,
                    debug_pm_status_path=debug_pm_status_path,
                    debug_pm=debug_pm,
                    debug_pm_max_requests=debug_pm_max_requests,
                    debug_pm_max_children=debug_pm_max_children,
                    debug_pm_start_servers=debug_pm_start_servers,
                    debug_pm_min_spare_servers=debug_pm_min_spare_servers,
                    debug_pm_max_spare_servers=debug_pm_max_spare_servers,
                    debug_request_terminate_timeout=debug_request_terminate,
                    debug_xdebug_profiler_enable_trigger=debug_xdebug)
        self.app.render((data), 'info_php.mustache')

    @expose(hide=True)
    def info_mysql(self):
        """Display MySQL information"""
        version = os.popen("mysql -V | awk '{print($5)}' | cut -d ',' "
                           "-f1 | tr -d '\n'").read()
        host = "localhost"
        port = os.popen("mysql -e \"show variables\" | grep ^port | awk "
                        "'{print($2)}' | tr -d '\n'").read()
        wait_timeout = os.popen("mysql -e \"show variables\" | grep "
                                "^wait_timeout | awk '{print($2)}' | "
                                "tr -d '\n'").read()
        interactive_timeout = os.popen("mysql -e \"show variables\" | grep "
                                       "^interactive_timeout | awk "
                                       "'{print($2)}' | tr -d '\n'").read()
        max_used_connections = os.popen("mysql -e \"show global status\" | "
                                        "grep Max_used_connections | awk "
                                        "'{print($2)}' | tr -d '\n'").read()
        datadir = os.popen("mysql -e \"show variables\" | grep datadir | awk"
                           " '{print($2)}' | tr -d '\n'").read()
        socket = os.popen("mysql -e \"show variables\" | grep \"^socket\" | "
                          "awk '{print($2)}' | tr -d '\n'").read()
        data = dict(version=version, host=host, port=port,
                    wait_timeout=wait_timeout,
                    interactive_timeout=interactive_timeout,
                    max_used_connections=max_used_connections,
                    datadir=datadir, socket=socket)
        self.app.render((data), 'info_mysql.mustache')

    @expose(hide=True)
    def default(self):
        """default function for info"""
        if (not self.app.pargs.nginx and not self.app.pargs.php
           and not self.app.pargs.mysql and not self.app.pargs.php7):
            self.app.pargs.nginx = True
            self.app.pargs.php = True
            self.app.pargs.mysql = True
            if EEAptGet.is_installed(self, 'php7.0-fpm'):
                    self.app.pargs.php = True

        if self.app.pargs.nginx:
            if EEAptGet.is_installed(self, 'nginx-custom') or EEAptGet.is_installed(self, 'nginx-common'):
                self.info_nginx()
            else:
                Log.error(self, "Nginx is not installed")

        if self.app.pargs.php:
            if (EEVariables.ee_platform_distro == 'debian' or EEVariables.ee_platform_codename == 'precise'):
                if EEAptGet.is_installed(self, 'php5-fpm'):
                    self.info_php()
                else:
                    Log.error(self, "PHP5 is not installed")
            else:
                if EEAptGet.is_installed(self, 'php5.6-fpm'):
                    self.info_php()
                else:
                    Log.error(self, "PHP5.6 is not installed")

        if self.app.pargs.php7:
            if EEAptGet.is_installed(self, 'php7.0-fpm'):
                self.info_php7()
            else:
                Log.error(self, "PHP 7.0 is not installed")

        if self.app.pargs.mysql:
            if EEShellExec.cmd_exec(self, "mysqladmin ping"):
                self.info_mysql()
            else:
                Log.error(self, "MySQL is not installed")


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EEInfoController)

    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', ee_info_hook)
