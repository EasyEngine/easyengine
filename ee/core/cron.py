from crontab import *
from ee.core.shellexec import EEShellExec
from ee.core.logging import Log

"""
Set CRON on LINUX system.
https://pypi.python.org/pypi/python-crontab
"""

class EECron():
    def setcron_daily(self,cmd,comment='Cron set by EasyEngine',user='root',min=0,hour=12):
        if not EEShellExec.cmd_exec(self, "crontab -l | grep -q \'{0}\'".format(cmd)):
            tab = CronTab(user=user)
            cron_job = tab.new(cmd, comment=comment)
            cron_job.minute.on(min)
            cron_job.hour.on(hour)
            #writes to crontab
            tab.write()
            Log.debug(self, "Cron is set:\n" + tab.render())
        else:
            Log.debug(self, "Cron already exist")


