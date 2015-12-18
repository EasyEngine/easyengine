from crontab import *
from ee.core.logging import Log

"""
Set CRON on LINUX system.
"""

class EECron():
    def setcron_daily(self,cmd,comment='Cron set by EasyEngine',user='root',min=0,hour=12):
        tab = CronTab(user=user)
        cron_job = tab.new(cmd, comment=comment)
        cron_job.minute().on(min)
        cron_job.hour().on(hour)
        #writes to crontab
        tab.write()
        Log.debug(self, "Cron is set:\n" + tab.render())
