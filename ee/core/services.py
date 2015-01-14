"""EasyEngine service start/stop/restart module."""
import os
import sys
import subprocess
from subprocess import Popen
from ee.core.logging import Log
import pystache


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
                    Log.info(self, "Started : {0:10}{1}"
                             .format(service_name, "[OK]"))
                else:
                    Log.error(self, retcode[1])
            except OSError as e:
                Log.error(self, "Failed to start service  {0} {1} {2}"
                          .format(e.errno, e.strerror, "[FAIL]"))
                return False

    def stop_service(self, service_name):
            try:
                retcode = subprocess.getstatusoutput('service {0} stop'
                                                     .format(service_name))
                if retcode[0] == 0:
                    Log.info(self, "Stopped : {0:10}{1}"
                             .format(service_name, "[OK]"))
                    return True
                else:
                    return False
            except OSError as e:
                Log.error(self, "Failed to stop service : {0}{1}{2}"
                          .format(e.errno, e.strerror, "[FAIL]"))
                return False

    def restart_service(self, service_name):
            try:
                EEService.stop_service(self, service_name)
                EEService.start_service(self, service_name)
                Log.info(self, "restart : {0:10}{1}"
                         .format(service_name, "[OK]"))
            except OSError as e:
                Log.error(self, "Failed to restart services \{0} {1} {2}"
                          .format(e.errno, e.strerror, "[FAIL]"))

    def reload_service(self, service_name):
            try:
                if service_name in ['nginx', 'php5-fpm']:
                    retcode = subprocess.getstatusoutput('{0} -t'
                                                         .format(service_name))
                    if retcode[0] == 0:
                        subprocess.getstatusoutput('service {0} reload'
                                                   .format(service_name))
                        Log.info(self, "reload : {0} {0:10}"
                                 .format(service_name, "[OK]"))
                        return True
                    else:
                        Log.error(self, "reload : {0}{1}"
                                  .format(service_name, "[FAIL]"))
                        Log.debug("{0}".format(retcode[1]))
                        return False

                retcode = subprocess.getstatusoutput('service {0} reload'
                                                     .format(service_name))
                if retcode[0] == 0:
                    Log.info(self, "reload : {0:10}{1}"
                             .format(service_name, "[OK]"))
                    return True
                else:
                    return False
            except OSError as e:
                    Log.error(self, "Failed to reload {0} {1} {2}"
                              .format(service_name, e, "[FAIL]"))
                    sys.exit(1)

    def get_service_status(self, service_name):
        try:
            is_exist = subprocess.getstatusoutput('which {0}'
                                                  .format(service_name))
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
            Log.error(self, "Unable to get services status \ {0}{1}{2}"
                      .format(e.errno, e.strerror, "[FAIL]"))
            return False
