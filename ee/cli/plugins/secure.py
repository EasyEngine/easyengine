from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.shellexec import EEShellExec
from ee.core.variables import EEVariables
import string
import random
import sys
import hashlib
import getpass
from ee.core.logging import Log


def secure_plugin_hook(app):
    # do something with the ``app`` object here.
    pass


class EEsecureController(CementBaseController):
    class Meta:
        label = 'secure'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = ('clean command cleans different cache with following '
                       'options')
        arguments = [
            (['--auth'],
                dict(help='secure auth', action='store_true')),
            (['--port'],
                dict(help='secure port', action='store_true')),
            (['--ip'],
                dict(help='secure ip', action='store_true'))
            ]

    @expose(hide=True)
    def default(self):
            if self.app.pargs.auth:
                self.secure_auth()
            if self.app.pargs.port:
                self.secure_port()
            if self.app.pargs.ip:
                self.secure_ip()

    @expose(hide=True)
    def secure_auth(self):
        passwd = ''.join([random.choice
                 (string.ascii_letters + string.digits)
                 for n in range(6)])
        username = input("Provide HTTP authentication user "
                         "name [{0}] :".format(EEVariables.ee_user))
        password = input("Provide HTTP authentication "
                         "password [{0}]".format(passwd))
        if username == "":
            username = EEVariables.ee_user
            Log.info(self, "HTTP authentication username:{username}"
                     .format(username=username))
        if password == "":
            password = passwd
            Log.info(self, "HTTP authentication password:{password}"
                     .format(password=password))
        EEShellExec.cmd_exec(self, "printf \"{username}:"
                             "$(openssl passwd -crypt "
                             "{password} 2> /dev/null)\n\""
                             "> /etc/nginx/htpasswd-ee 2>/dev/null"
                             .format(username=username,
                                     password=password))

    @expose(hide=True)
    def secure_port(self):
        port = input("EasyEngine admin port [22222]:")
        if port == "":
            port = 22222
        if EEVariables.ee_platform_distro == 'Ubuntu':
            EEShellExec.cmd_exec(self, "sed -i \"s/listen.*/listen "
                                 "{port} default_server ssl spdy;/\" "
                                 "/etc/nginx/sites-available/22222"
                                 .format(port=port))
        else:
            Log.info(self, "Unable to change EasyEngine admin port{0}"
                     .format("[FAIL]"))
        if EEVariables.ee_platform_distro == 'Debian':
            EEShellExec.cmd_exec(self, "sed -i \"s/listen.*/listen "
                                 "{port} default_server ssl;/\" "
                                 "/etc/nginx/sites-available/22222"
                                 .format(port=port))
        else:
            Log.info(self, "Unable to change EasyEngine admin port{0}"
                     .format("[FAIL]"))

    @expose(hide=True)
    def secure_ip(self):
        #TODO:remaining with ee.conf updation in file
        newlist = []
        ip = input("Enter the comma separated IP addresses "
                   "to white list [127.0.0.1]:")
        try:
            user_list_ip = ip.split(',')
        except Exception as e:
            ip = ['127.0.0.1']
        self.app.config.set('mysql', 'grant-host', "hello")
        exist_ip_list = self.app.config.get('stack', 'ip-address').split()
        print(exist_ip_list)
        for check_ip in user_list_ip:
            if check_ip not in exist_ip_list:
                newlist.extend(exist_ip_list)
        # changes in acl.conf file
            if len(newlist) != 0:
                EEShellExec.cmd_exec(self, "sed -i \"/allow.*/d\" /etc/nginx"
                                     "/common/acl.conf")
            for whitelist_adre in newlist:
                EEShellExec.cmd_exec(self, "sed -i \"/deny/i "
                                     "echo allow {whitelist_adre}\\;\" "
                                     "/etc/nginx/common/acl.conf"
                                     .format(whitelist_adre=whitelist_adre))


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EEsecureController)
    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', secure_plugin_hook)
