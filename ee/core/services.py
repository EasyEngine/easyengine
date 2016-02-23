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
        pass

    def start_service(self, service_name):
        """
            start service
            Similar to `service xyz start`
        """
        try:
            if service_name in ['nginx', 'php5-fpm']:
                service_cmd = ('{0} -t && service {0} start'
                               .format(service_name))
            else:
                service_cmd = ('service {0} start'.format(service_name))

            Log.info(self, "Start : {0:10}" .format(service_name), end='')
            retcode = subprocess.getstatusoutput(service_cmd)
            if retcode[0] == 0:
                Log.info(self, "[" + Log.ENDC + "OK" + Log.OKBLUE + "]")
                return True
            else:
                Log.debug(self, "{0}".format(retcode[1]))
                Log.info(self, "[" + Log.FAIL + "Failed" + Log.OKBLUE+"]")
                return False
        except OSError as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "\nFailed to start service   {0}"
                      .format(service_name))

    def stop_service(self, service_name):
        """
            Stop service
            Similar to `service xyz stop`
        """
        try:
            Log.info(self, "Stop : {0:10}" .format(service_name), end='')
            retcode = subprocess.getstatusoutput('service {0} stop'
                                                 .format(service_name))
            if retcode[0] == 0:
                Log.info(self, "[" + Log.ENDC + "OK" + Log.OKBLUE + "]")
                return True
            else:
                Log.debug(self, "{0}".format(retcode[1]))
                Log.info(self, "[" + Log.FAIL + "Failed" + Log.OKBLUE+"]")
                return False
        except OSError as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "\nFailed to stop service : {0}"
                      .format(service_name))

    def restart_service(self, service_name):
        """
            Restart service
            Similar to `service xyz restart`
        """
        try:
            if service_name in ['nginx', 'php5-fpm']:
                service_cmd = ('{0} -t && service {0} restart'
                               .format(service_name))
            else:
                service_cmd = ('service {0} restart'.format(service_name))

            Log.info(self, "Restart : {0:10}".format(service_name), end='')
            retcode = subprocess.getstatusoutput(service_cmd)
            if retcode[0] == 0:
                Log.info(self, "[" + Log.ENDC + "OK" + Log.OKBLUE + "]")
                return True
            else:
                Log.debug(self, "{0}".format(retcode[1]))
                Log.info(self, "[" + Log.FAIL + "Failed" + Log.OKBLUE+"]")
                return False
        except OSError as e:
            Log.debug(self, "{0} {1}".format(e.errno, e.strerror))
            Log.error(self, "\nFailed to restart service : {0}"
                      .format(service_name))

    def reload_service(self, service_name):
        """
            Stop service
            Similar to `service xyz stop`
        """
        try:
            if service_name in ['nginx', 'php5-fpm']:
                service_cmd = ('{0} -t && service {0} reload'
                               .format(service_name))
            else:
                service_cmd = ('service {0} reload'.format(service_name))

            Log.info(self, "Reload : {0:10}".format(service_name), end='')
            retcode = subprocess.getstatusoutput(service_cmd)
            if retcode[0] == 0:
                    Log.info(self, "[" + Log.ENDC + "OK" + Log.OKBLUE + "]")
                    return True
            else:
                Log.debug(self, "{0}".format(retcode[1]))
                Log.info(self, "[" + Log.FAIL + "Failed" + Log.OKBLUE+"]")
                return False
        except OSError as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "\nFailed to reload service {0}"
                      .format(service_name))

    def get_service_status(self, service_name):


        try:
            is_exist = subprocess.getstatusoutput('which {0}'
                                                  .format(service_name))
            if is_exist[0] == 0 or service_name in ['php7.0-fpm', 'php5.6-fpm']:
                retcode = subprocess.getstatusoutput('service {0} status'
                                                     .format(service_name))
                if retcode[0] == 0:
                    return True
                else:
                    Log.debug(self, "{0}".format(retcode[1]))
                    return False
            else:
                return False
        except OSError as e:
            Log.debug(self, "{0}{1}".format(e.errno, e.strerror))
            Log.error(self, "Unable to get services status of {0}"
                      .format(service_name))
            return False
