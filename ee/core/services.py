"""EasyEngine service start/stop/restart module."""
import os
import sys
import subprocess
from subprocess import Popen


class EEService():
    """Intialization for service"""
    def ___init__():
        # TODO method for services
        pass

    def start_service(self, service_name):
            try:
                retcode = subprocess.getstatusoutput('service {0} start'
                                                     .format(service_name))
                if retcode[0] == 0:
                        print("Started : {0}".format(service_name))
                else:
                    self.app.log.error(retcode[1])
            except OSError as e:
                self.app.log.error("Execution failed:",  e)
                return False

    def stop_service(self, service_name):
            try:
                retcode = subprocess.getstatusoutput('service {0} stop'
                                                     .format(service_name))
                if retcode[0] == 0:
                    print("Stopped : {0}".format(service_name))
                    return True
                else:
                    return False
            except OSError as e:
                self.app.log.error("Execution failed:", e)
                return False

    def restart_service(self, service_name):
            try:
                EEService.stop_service(service_name)
                EEService.start_service(service_name)
            except OSError as e:
                self.app.log.error("Execution failed:",  e)

    def get_service_status(self, service_name):
        try:
            is_exist = subprocess.getstatusoutput('which {0}'
                                                  .format(service_name))[0]
            if is_exist == 0:
                retcode = subprocess.getstatusoutput('service {0} status'
                                                     .format(service_name))
                if retcode[0] == 0:
                    return True
                else:
                    return False
            else:
                return False
        except OSError as e:
            return False
