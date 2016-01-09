"""EasyEngine shell executaion functions."""
from ee.core.logging import Log
import os
import sys
import subprocess


class CommandExecutionError(Exception):
    """custom Exception for command execution"""
    pass


class EEShellExec():
    """Method to run shell commands"""
    def __init__():
        pass

    def cmd_exec(self, command, errormsg='', log=True):
        """Run shell command from Python"""
        try:
            log and Log.debug(self, "Running command: {0}".format(command))

            with subprocess.Popen([command], stdout=subprocess.PIPE,
                                  stderr=subprocess.PIPE, shell=True) as proc:
                (cmd_stdout_bytes, cmd_stderr_bytes) = proc.communicate()
                (cmd_stdout, cmd_stderr) = (cmd_stdout_bytes.decode('utf-8',
                                            "replace"),
                                            cmd_stderr_bytes.decode('utf-8',
                                            "replace"))

            if proc.returncode == 0:
                Log.debug(self, "Command Output: {0}, \nCommand Error: {1}"
                                .format(cmd_stdout, cmd_stderr))
                return True
            else:
                Log.debug(self, "Command Output: {0}, \nCommand Error: {1}"
                                .format(cmd_stdout, cmd_stderr))
                return False
        except OSError as e:
                Log.debug(self, str(e))
                raise CommandExecutionError
        except Exception as e:
                Log.debug(self, str(e))
                raise CommandExecutionError

    def invoke_editor(self, filepath, errormsg=''):
        """
            Open files using sensible editor
        """
        try:
            subprocess.call(['sensible-editor', filepath])
        except OSError as e:
                Log.debug(self, "{0}{1}".format(e.errno, e.strerror))
                raise CommandExecutionError


    def cmd_exec_stdout(self, command, errormsg='', log=True):
        """Run shell command from Python"""
        try:
            log and Log.debug(self, "Running command: {0}".format(command))

            with subprocess.Popen([command], stdout=subprocess.PIPE,
                                  stderr=subprocess.PIPE, shell=True) as proc:
                (cmd_stdout_bytes, cmd_stderr_bytes) = proc.communicate()
                (cmd_stdout, cmd_stderr) = (cmd_stdout_bytes.decode('utf-8',
                                            "replace"),
                                            cmd_stderr_bytes.decode('utf-8',
                                            "replace"))

            if proc.returncode == 0:
                Log.debug(self, "Command Output: {0}, \nCommand Error: {1}"
                                .format(cmd_stdout, cmd_stderr))
                return cmd_stdout
            else:
                Log.debug(self, "Command Output: {0}, \nCommand Error: {1}"
                                .format(cmd_stdout, cmd_stderr))
                return cmd_stdout
        except OSError as e:
                Log.debug(self, str(e))
                raise CommandExecutionError
        except Exception as e:
                Log.debug(self, str(e))
                raise CommandExecutionError