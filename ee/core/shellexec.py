"""EasyEngine shell executaion functions."""
from subprocess import Popen
from ee.core.logging import Log
import os
import sys
import subprocess


class EEShellExec():
    """Method to run shell commands"""
    def __init__():
        pass

    def cmd_exec(self, command, errormsg='', log=True):
        """Run shell command from Python"""
        try:
            if log:
                Log.debug(self, "Running command: {0}".format(command))
            retcode = subprocess.getstatusoutput(command)
            if retcode[0] == 0:
                return True
            else:
                Log.debug(self, retcode[1])
                return False
        except OSError as e:
            if errormsg:
                Log.error(self, errormsg)
            else:
                Log.debug(self, "{0}".format(e))
                Log.error(self, "Unable to execute command {0}"
                          .format(command))
        except Exception as e:
            if errormsg:
                Log.error(self, errormsg)
            else:
                Log.debug(self, "{0}".format(e))
                Log.error(self, "Unable to execute command {0}"
                          .format(command))

    def invoke_editor(self, filepath, errormsg=''):
        """
            Open files using sensible editor
        """
        try:
            subprocess.call(['sensible-editor', filepath])
        except OSError as e:
            if errormsg:
                Log.error(self, errormsg)
            else:
                Log.debug(self, "{0}{1}".format(e.errno, e.strerror))
                Log.error(self, "Unable to edit file {0}".format(filepath))
