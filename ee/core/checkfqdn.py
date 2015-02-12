from ee.core.shellexec import EEShellExec
from ee.core.variables import EEVariables
import os


def check_fqdn(self, ee_host):
    """FQDN check with EasyEngine, for mail server hostname must be FQDN"""
    # ee_host=os.popen("hostname -f | tr -d '\n'").read()
    if '.' in ee_host:
        EEVariables.ee_fqdn = ee_host
        with open('/etc/hostname', encoding='utf-8', mode='w') as hostfile:
            hostfile.write(ee_host)

        EEShellExec.cmd_exec(self, "sed -i \"1i\\127.0.0.1 {0}\" /etc/hosts"
                                   .format(ee_host))
        if EEVariables.ee_platform_distro == 'debian':
            EEShellExec.cmd_exec(self, "/etc/init.d/hostname.sh start")
        else:
            EEShellExec.cmd_exec(self, "service hostname restart")

    else:
        ee_host = input("Enter hostname [fqdn]:")
        check_fqdn(self, ee_host)
