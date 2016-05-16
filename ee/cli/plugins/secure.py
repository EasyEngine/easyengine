from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.aptget import EEAptGet
from ee.core.shellexec import EEShellExec
from ee.core.variables import EEVariables
from ee.core.logging import Log
from ee.core.git import EEGit
from ee.core.services import EEService
import string
import random
import sys
import hashlib
import getpass


def ee_secure_hook(app):
    # do something with the ``app`` object here.
    pass


class EESecureController(CementBaseController):
    class Meta:
        label = 'secure'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = ('Secure command secure auth, ip and port')
        arguments = [
            (['--auth'],
                dict(help='secure auth', action='store_true')),
            (['--port'],
                dict(help='secure port', action='store_true')),
            (['--ip'],
                dict(help='secure ip', action='store_true')),
            (['user_input'],
                dict(help='user input', nargs='?', default=None)),
            (['user_pass'],
                dict(help='user pass', nargs='?', default=None))]
        usage = "ee secure [options]"

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
        """This function Secures authentication"""
        passwd = ''.join([random.choice
                         (string.ascii_letters + string.digits)
                         for n in range(6)])
        if not self.app.pargs.user_input:
            username = input("Provide HTTP authentication user "
                             "name [{0}] :".format(EEVariables.ee_user))
            self.app.pargs.user_input = username
            if username == "":
                self.app.pargs.user_input = EEVariables.ee_user
        if not self.app.pargs.user_pass:
            password = getpass.getpass("Provide HTTP authentication "
                                       "password [{0}] :".format(passwd))
            self.app.pargs.user_pass = password
            if password == "":
                self.app.pargs.user_pass = passwd
        Log.debug(self, "printf username:"
                  "$(openssl passwd -crypt "
                  "password 2> /dev/null)\n\""
                  "> /etc/nginx/htpasswd-ee 2>/dev/null")
        EEShellExec.cmd_exec(self, "printf \"{username}:"
                             "$(openssl passwd -crypt "
                             "{password} 2> /dev/null)\n\""
                             "> /etc/nginx/htpasswd-ee 2>/dev/null"
                             .format(username=self.app.pargs.user_input,
                                     password=self.app.pargs.user_pass),
                             log=False)
        EEGit.add(self, ["/etc/nginx"],
                  msg="Adding changed secure auth into Git")

    @expose(hide=True)
    def secure_port(self):
        """This function Secures port"""
        if self.app.pargs.user_input:
            while not self.app.pargs.user_input.isdigit():
                Log.info(self, "Please Enter valid port number ")
                self.app.pargs.user_input = input("EasyEngine "
                                                  "admin port [22222]:")
        if not self.app.pargs.user_input:
            port = input("EasyEngine admin port [22222]:")
            if port == "":
                self.app.pargs.user_input = 22222
            while not port.isdigit() and port != "":
                Log.info(self, "Please Enter valid port number :")
                port = input("EasyEngine admin port [22222]:")
            self.app.pargs.user_input = port
        if EEVariables.ee_platform_distro == 'ubuntu':
            EEShellExec.cmd_exec(self, "sed -i \"s/listen.*/listen "
                                 "{port} default_server ssl http2;/\" "
                                 "/etc/nginx/sites-available/22222"
                                 .format(port=self.app.pargs.user_input))
        if EEVariables.ee_platform_distro == 'debian':
            EEShellExec.cmd_exec(self, "sed -i \"s/listen.*/listen "
                                 "{port} default_server ssl http2;/\" "
                                 "/etc/nginx/sites-available/22222"
                                 .format(port=self.app.pargs.user_input))
        EEGit.add(self, ["/etc/nginx"],
                  msg="Adding changed secure port into Git")
        if not EEService.reload_service(self, 'nginx'):
            Log.error(self, "service nginx reload failed. "
                      "check issues with `nginx -t` command")
        Log.info(self, "Successfully port changed {port}"
                 .format(port=self.app.pargs.user_input))

    @expose(hide=True)
    def secure_ip(self):
        """This function Secures IP"""
        # TODO:remaining with ee.conf updation in file
        newlist = []
        if not self.app.pargs.user_input:
            ip = input("Enter the comma separated IP addresses "
                       "to white list [127.0.0.1]:")
            self.app.pargs.user_input = ip
        try:
            user_ip = self.app.pargs.user_input.split(',')
        except Exception as e:
            user_ip = ['127.0.0.1']
        for ip_addr in user_ip:
            if not ("exist_ip_address "+ip_addr in open('/etc/nginx/common/'
                    'acl.conf').read()):
                EEShellExec.cmd_exec(self, "sed -i "
                                     "\"/deny/i allow {whitelist_adre}\;\""
                                     " /etc/nginx/common/acl.conf"
                                     .format(whitelist_adre=ip_addr))
        EEGit.add(self, ["/etc/nginx"],
                  msg="Adding changed secure ip into Git")

        Log.info(self, "Successfully added IP address in acl.conf file")


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EESecureController)
    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', ee_secure_hook)
