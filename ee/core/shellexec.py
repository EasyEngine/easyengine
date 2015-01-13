"""EasyEngine shell executaion functions."""
import os
import sys
import subprocess
from subprocess import Popen
from ee.core.logging import Log


class EEShellExec():
    """Method to run shell commands"""
    def __init__():
        pass

    def cmd_exec(self, command, errormsg=''):
        try:
            self.app.log.debug("Running command: {0}".format(command))
            retcode = subprocess.getstatusoutput(command)
            if retcode[0] == 0:
                return True
            else:
                self.app.log.debug(retcode[1])
                return False
        except OSError as e:
            if errormsg:
                Log.error(self, errormsg)
            else:
                Log.error(self, "Unable to execute command \ {0}{1}"
                          .format(e.errno, e.strerror))
            Log.debug(self, "Unable to execute command \ {0}{1}"
                      .format(e.errno, e.strerror))
            sys.exit(1)

    def invoke_editor(self, filepath, errormsg=''):
        try:
            subprocess.call(['sensible-editor', filepath])
        except OSError as e:
            if errormsg:
                Log.error(self, errormsg)
            else:
                Log.error(self, "Unable to edit file \ {0}{1}"
                          .format(e.errno, e.strerror))
            Log.debug(self, "Unable to edit file \ {0}{1}"
                      .format(e.errno, e.strerror))
            sys.exit(1)
