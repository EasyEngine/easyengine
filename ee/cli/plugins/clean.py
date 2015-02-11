"""Clean Plugin for EasyEngine."""

from ee.core.shellexec import EEShellExec
from ee.core.aptget import EEAptGet
from ee.core.services import EEService
from ee.core.logging import Log
from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
import os
import urllib.request


def clean_plugin_hook(app):
    # do something with the ``app`` object here.
    pass


class EECleanController(CementBaseController):
    class Meta:
        label = 'clean'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = ('Clean NGINX FastCGI cache, Opcacache, Memcache')
        arguments = [
            (['--all'],
                dict(help='Clean all cache', action='store_true')),
            (['--fastcgi'],
                dict(help='Clean FastCGI cache', action='store_true')),
            (['--memcache'],
                dict(help='Clean MemCache', action='store_true')),
            (['--opcache'],
                dict(help='Clean OpCache', action='store_true'))
            ]

    @expose(hide=True)
    def default(self):
        if (not (self.app.pargs.all or self.app.pargs.fastcgi or
                 self.app.pargs.memcache or self.app.pargs.opcache)):
            self.clean_fastcgi()
        if self.app.pargs.all:
            self.clean_memcache()
            self.clean_fastcgi()
            self.clean_opcache()
        if self.app.pargs.fastcgi:
            self.clean_fastcgi()
        if self.app.pargs.memcache:
            self.clean_memcache()
        if self.app.pargs.opcache:
            self.clean_opcache()

    @expose(hide=True)
    def clean_memcache(self):
        """This function Clears memcache"""
        try:
            if(EEAptGet.is_installed(self, "memcached")):
                EEService.restart_service(self, "memcached")
                Log.info(self, "Cleaning MemCache")
            else:
                Log.error(self, "Memcache not installed")
        except Exception as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "Unable to restart Memcached")

    @expose(hide=True)
    def clean_fastcgi(self):
        """This function clears Fastcgi cache"""
        if(os.path.isdir("/var/run/nginx-cache")):
            Log.info(self, "Cleaning NGINX FastCGI cache")
            EEShellExec.cmd_exec(self, "rm -rf /var/run/nginx-cache/*")
        else:
            Log.error(self, "Unable to clean FastCGI cache")

    @expose(hide=True)
    def clean_opcache(self):
        """This function clears opcache"""
        try:
            Log.info(self, "Cleaning opcache")
            wp = urllib.request.urlopen(" https://127.0.0.1:22222/cache"
                                        "/opcache/opgui.php?page=reset").read()
        except Exception as e:
                Log.debug(self, "{0}".format(e))
                Log.error(self, "Unable to clean OpCache")


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EECleanController)
    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', clean_plugin_hook)
