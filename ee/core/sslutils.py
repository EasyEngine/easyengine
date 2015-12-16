from ee.core.shellexec import EEShellExec


class SSL:

    def getExpirationDays(self,domain):
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
        expiration_date =  EEShellExec.cmd_exec(self, "date -d \"\`openssl x509 -in /etc/letsencrypt/live/{0}/cert.pem"
                                           " -text -noout|grep \"Not After\"|cut -c 25-`\" ".format(domain))
        return expiration_date

   