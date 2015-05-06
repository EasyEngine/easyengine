"""EasyEngine apt-get update GPG Key fix module"""

from ee.core.apt_repo import EERepo
import subprocess


def gpgkeyfix(self):
    try:
        # Run apt-get update
        sub = subprocess.Popen('apt-get update', stdout=subprocess.PIPE,
                               stderr=subprocess.PIPE, shell=True)
        sub.wait()

        output, error_output = sub.communicate()
        # Check what is error in error_output
        if "NO_PUBKEY" in str(error_output):
            # Split the output
            error_list = str(error_output).split("\\n")

            # Use a loop to add misising keys
            for single_error in error_list:
                if "NO_PUBKEY" in single_error:
                    key = single_error.rsplit(None, 1)[-1]
                    EERepo.add_key(self, key)
    except Exception as e:
        Log.error(self, "Error while fixing GPG keys")
