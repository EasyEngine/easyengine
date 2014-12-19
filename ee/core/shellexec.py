"""EasyEngine shell executaion functions."""
import os
import sys
import subprocess
from subprocess import Popen


class EEShellExec():
    """Method to run shell commands"""
    def __init__():
        pass

    def cmd_exec(command):
        try:
            retcode = subprocess.getstatusoutput(command)
        except OSError as e:
            print(e)
