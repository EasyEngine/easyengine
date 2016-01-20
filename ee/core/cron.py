from ee.core.shellexec import EEShellExec
from ee.core.logging import Log

"""
Set CRON on LINUX system.
"""

class EECron():
    def setcron_weekly(self,cmd,comment='Cron set by EasyEngine',user='root',min=0,hour=12):
        if not EEShellExec.cmd_exec(self, "crontab -l | grep -q \'{0}\'".format(cmd)):

            EEShellExec.cmd_exec(self, "/bin/bash -c \"crontab -l "
                                             "2> /dev/null | {{ cat; echo -e"
                                             " \\\""
                                             "\\n0 0 * * 0 "
                                             "{0}".format(cmd) +
                                             " # {0}".format(comment)+
                                             "\\\"; } | crontab -\"")
            Log.debug(self, "Cron set")



    def remove_cron(self,cmd):
        if EEShellExec.cmd_exec(self, "crontab -l | grep -q \'{0}\'".format(cmd)):
            if not EEShellExec.cmd_exec(self, "/bin/bash -c "
                                                    "\"crontab "
                                                    "-l | sed '/{0}/d'"
                                                    "| crontab -\""
                                                    .format(cmd)):
                Log.error(self, "Failed to remove crontab entry",False)
        else:
            Log.debug(self, "Cron not found")
