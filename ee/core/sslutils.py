import os
from ee.core.shellexec import EEShellExec
from ee.core.logging import Log


class SSL:

   def getExpirationDays(self,domain):
        # check if exist
        if not os.path.isfile('/etc/letsencrypt/live/{0}/cert.pem'
                      .format(domain)):
            Log.error(self,'File Not Found : /etc/letsencrypt/live/{0}/cert.pem'
                      .format(domain),False)
            Log.error(self, "Check logs for reason "
                      "`tail /var/log/ee/ee.log` & Try Again!!!")


        current_date = EEShellExec.cmd_exec(self, "date -d \"now\" +%s")
        expiration_date =  EEShellExec.cmd_exec(self, "date -d \"\`openssl x509 -in /etc/letsencrypt/live/{0}/cert.pem"
                                           " -text -noout|grep \"Not After\"|cut -c 25-`\" +%s".format(domain))

        days_left = (current_date - expiration_date)*0.000011574
        if (days_left > 0):
            return days_left
        else:
            # return "Certificate Already Expired ! Please Renew soon."
            return -1

   def getExpirationDate(self,domain):
        # check if exist
        if not os.path.isfile('/etc/letsencrypt/live/{0}/cert.pem'
                      .format(domain),False):
            Log.error(self,'File Not Found : /etc/letsencrypt/live/{0}/cert.pem'
                      .format(domain))
            Log.error(self, "Check logs for reason "
                      "`tail /var/log/ee/ee.log` & Try Again!!!")

        expiration_date =  EEShellExec.cmd_exec(self, "date -d \"\`openssl x509 -in /etc/letsencrypt/live/{0}/cert.pem"
                                           " -text -noout|grep \"Not After\"|cut -c 25-`\" ".format(domain))
        return expiration_date

