"""EasyEngine shell executaion functions."""
from ee.core.logging import Log
import os
import sys
import subprocess
import shlex


class EEShellExec():
    """Method to run shell commands"""
    def __init__():
        pass

    def cmd_exec(self, command, errormsg='', log=True):
        """Run shell command from Python"""
        try:
            if log:
                Log.debug(self, "Running command: {0}".format(command))
            args = shlex.split(command)
            with subprocess.Popen(args, stdout=subprocess.PIPE,
                                  stderr=subprocess.PIPE) as proc:
                (cmd_stdout_bytes, cmd_stderr_bytes) = proc.communicate()
                (cmd_stdout, cmd_stderr) = (cmd_stdout_bytes.decode('utf-8',
                                            "replace"),
                                            cmd_stderr_bytes.decode('utf-8',
                                            "replace"))

            if proc.returncode == 0:
                return True
            else:
                Log.debug(self, "Command Output: {0}, Command Error: {1}"
                                .format(cmd_stdout, cmd_stderr))
                return False
        except OSError as e:
            if errormsg:
                Log.error(self, errormsg)
            else:
                Log.debug(self, "Unable to execute command {0}"
                          .format(command))
                Log.debug(self, "{0}".format(e))
                Log.error(self, "Error occured while executing command")
        except Exception as e:
            if errormsg:
                Log.error(self, errormsg)
            else:
                Log.debug(self, "Unable to execute command {0}"
                          .format(command))
                Log.debug(self, "{0}".format(e))
                Log.error(self, "Error occurred while executing command")

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
