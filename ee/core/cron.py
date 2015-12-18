from crontab import *
from ee.core.shellexec import EEShellExec
from ee.core.logging import Log

"""
Set CRON on LINUX system.
"""

class EECron():
    def setcron_daily(self,cmd,comment='Cron set by EasyEngine',user='root',min=0,hour=12):
        if not self.check_isexist(self,cmd):
            tab = CronTab(user=user)
            cron_job = tab.new(cmd, comment=comment)
            cron_job.minute().on(min)
            cron_job.hour().on(hour)
            #writes to crontab
            tab.write()
            Log.debug(self, "Cron is set:\n" + tab.render())

    #Check if cron already exist
    def check_isexist(self,cmd):

         if EEShellExec.cmd_exec(self, "crontab -l | grep -q \'{0}\'".format(cmd)):
             Log.debug(self, "Cron already exist")
             return True
         else:
             return False