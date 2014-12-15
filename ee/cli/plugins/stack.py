"""Example Plugin for EasyEngine."""

from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.variables import EEVariables
from ee.core.aptget import EEAptGet
from ee.core.download import EEDownload
from ee.core.shellexec import EEShellExec
import random
import string


def ee_stack_hook(app):
    # do something with the ``app`` object here.
    pass


class EEStackController(CementBaseController):
    class Meta:
        label = 'stack'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = 'stack command manages stack operations'
        arguments = [
            (['--web'],
                dict(help='Install web stack', action='store_true')),
            (['--admin'],
                dict(help='Install admin tools stack', action='store_true')),
            (['--mail'],
                dict(help='Install mail server stack', action='store_true')),
            (['--nginx'],
                dict(help='Install Nginx stack', action='store_true')),
            (['--php'],
                dict(help='Install PHP stack', action='store_true')),
            (['--mysql'],
                dict(help='Install MySQL stack', action='store_true')),
            (['--postfix'],
                dict(help='Install Postfix stack', action='store_true')),
            ]

    @expose(hide=True)
    def default(self):
        # TODO Default action for ee stack command
        print("Inside EEStackController.default().")

    @expose(hide=True)
    def preseed_pref(self, packages):
        if "postfix" in packages:
            EEShellExec.cmd_exec("echo \"postfix postfix/main_mailer_type "
                                 "string 'Internet Site'\" | "
                                 "debconf-set-selections")
            EEShellExec.cmd_exec("echo \"postfix postfix/mailname string "
                                 "$(hostname -f)\" | debconf-set-selections")
        if "mysql" in packages:
            chars = ''.join(random.sample(string.letters, 8))
            EEShellExec.cmd_exec("echo \"percona-server-server-5.6 "
                                 "percona-server-server/root_password "
                                 "password {chars}\" | "
                                 "debconf-set-selections".format(chars=chars))
            EEShellExec.cmd_exec("echo \"percona-server-server-5.6 "
                                 "percona-server-server/root_password_again "
                                 "password {chars}\" | "
                                 "debconf-set-selections".format(chars=chars))

    @expose()
    def install(self):
        pkg = EEAptGet()
        packages = []
        if self.app.pargs.web:
            packages = (packages + EEVariables.ee_nginx + EEVariables.ee_php +
                        EEVariables.ee_mysql)
        if self.app.pargs.admin:
            pass
            #packages = packages + EEVariables.ee_nginx
        if self.app.pargs.mail:
            pass
            #packages = packages + EEVariables.ee_nginx
        if self.app.pargs.nginx:
            packages = packages + EEVariables.ee_nginx
        if self.app.pargs.php:
            packages = packages + EEVariables.ee_php
        if self.app.pargs.mysql:
            packages = packages + EEVariables.ee_mysql
        if self.app.pargs.postfix:
            packages = packages + EEVariables.ee_postfix
        self.preseed_pref(packages)
        pkg.install(packages)

    @expose()
    def remove(self):
        pkg = EEAptGet()
        packages = []
        if self.app.pargs.web:
            packages = (packages + EEVariables.ee_nginx + EEVariables.ee_php +
                        EEVariables.ee_mysql)
        if self.app.pargs.admin:
            pass
            #packages = packages + EEVariables.ee_nginx
        if self.app.pargs.mail:
            pass
            #packages = packages + EEVariables.ee_nginx
        if self.app.pargs.nginx:
            packages = packages + EEVariables.ee_nginx
        if self.app.pargs.php:
            packages = packages + EEVariables.ee_php
        if self.app.pargs.mysql:
            packages = packages + EEVariables.ee_mysql
        if self.app.pargs.postfix:
            packages = packages + EEVariables.ee_postfix
        print(packages)
        pkg.remove(packages)

    @expose()
    def purge(self):
        pkg = EEAptGet()
        packages = []
        if self.app.pargs.web:
            packages = (packages + EEVariables.ee_nginx + EEVariables.ee_php +
                        EEVariables.ee_mysql)
        if self.app.pargs.admin:
            pass
            #packages = packages + EEVariables.ee_nginx
        if self.app.pargs.mail:
            pass
            #packages = packages + EEVariables.ee_nginx
        if self.app.pargs.nginx:
            packages = packages + EEVariables.ee_nginx
        if self.app.pargs.php:
            packages = packages + EEVariables.ee_php
        if self.app.pargs.mysql:
            packages = packages + EEVariables.ee_mysql
        if self.app.pargs.postfix:
            packages = packages + EEVariables.ee_postfix
        print(packages)
        pkg.purge(packages)


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EEStackController)

    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', ee_stack_hook)
