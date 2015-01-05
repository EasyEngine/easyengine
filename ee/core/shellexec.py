"""EasyEngine shell executaion functions."""
import os
import sys
import subprocess
from subprocess import Popen


class EEShellExec():
    """Method to run shell commands"""
    def __init__():
        pass

    def cmd_exec(self, command):
        try:
            retcode = subprocess.getstatusoutput(command)
            if retcode[0] == 0:
                return True
            else:
                self.app.log.warn(retcode[1])
                return False
        except OSError as e:
            self.app.log.error("Unable to execute command \ {0}{1}"
                               .format(e.errno, e.strerror))
            return False
