"""EasyEngine apt-get update GPG Key fix module"""

import os
import subprocess


def gpgkeyfix(self):
    try:
        sub = subprocess.Popen('apt-get update', stdout=subprocess.PIPE,
                               stderr=subprocess.PIPE, shell=True)
        sub.wait()
        output, error_output = sub.communicate()
        if "NO_PUBKEY" in str(error_output):
            error_list = str(error_output).split("\\n")
            for single_error in error_list:
                if "NO_PUBKEY" in single_error:
                    key = single_error.rsplit(None, 1)[-1]
                    EERepo.add_key(self, key)
    except Exception as e:
        Log.error(self, "Error while fixing GPG keys")
