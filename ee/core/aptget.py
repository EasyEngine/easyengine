"""EasyEngine package installation using apt-get module."""
import apt
import apt_pkg
import sys
import subprocess
from ee.core.logging import Log
from ee.core.apt_repo import EERepo
from sh import apt_get
from sh import ErrorReturnCode


class EEAptGet():
    """Generic apt-get intialisation"""

    def update(self):
        """
        Similar to `apt-get update`
        """
        try:
            with open('/var/log/ee/ee.log', 'a') as f:
                proc = subprocess.Popen('apt-get update',
                                        shell=True,
                                        stdin=None, stdout=f,
                                        stderr=subprocess.PIPE,
                                        executable="/bin/bash")
                proc.wait()
                output, error_output = proc.communicate()

                # Check what is error in error_output
                if "NO_PUBKEY" in str(error_output):
                    # Split the output
                    Log.info(self, "Fixing missing GPG keys, please wait...")
                    error_list = str(error_output).split("\\n")

                    # Use a loop to add misising keys
                    for single_error in error_list:
                        if "NO_PUBKEY" in single_error:
                            key = single_error.rsplit(None, 1)[-1]
                            EERepo.add_key(self, key, keyserver="hkp://pgp.mit.edu")

                    proc = subprocess.Popen('apt-get update',
                                            shell=True,
                                            stdin=None, stdout=f, stderr=f,
                                            executable="/bin/bash")
                    proc.wait()

                if proc.returncode == 0:
                    return True
                else:
                    Log.info(self, Log.FAIL + "Oops Something went wrong!!")
                    Log.error(self, "Check logs for reason "
                              "`tail /var/log/ee/ee.log` & Try Again!!!")

        except Exception as e:
            Log.error(self, "apt-get update exited with error")

    def check_upgrade(self):
        """
        Similar to `apt-get upgrade`
        """
        try:
            check_update = subprocess.Popen(['apt-get upgrade -s | grep '
                                            '\"^Inst\" | wc -l'],
                                            stdout=subprocess.PIPE,
                                            shell=True).communicate()[0]
            if check_update == b'0\n':
                Log.error(self, "No package updates available")
            Log.info(self, "Following package updates are available:")
            subprocess.Popen("apt-get -s dist-upgrade | grep \"^Inst\"",
                             shell=True, executable="/bin/bash",
                             stdout=sys.stdout).communicate()

        except Exception as e:
            Log.error(self, "Unable to check for packages upgrades")

    def dist_upgrade(self):
        """
        Similar to `apt-get upgrade`
        """
        try:
            with open('/var/log/ee/ee.log', 'a') as f:
                proc = subprocess.Popen("DEBIAN_FRONTEND=noninteractive "
                                        "apt-get dist-upgrade -o "
                                        "Dpkg::Options::=\"--force-confdef\""
                                        " -o "
                                        "Dpkg::Options::=\"--force-confold\""
                                        " -y ",
                                        shell=True, stdin=None,
                                        stdout=f, stderr=f,
                                        executable="/bin/bash")
                proc.wait()

            if proc.returncode == 0:
                return True
            else:
                Log.info(self, Log.FAIL + "Oops Something went "
                         "wrong!!")
                Log.error(self, "Check logs for reason "
                          "`tail /var/log/ee/ee.log` & Try Again!!!")
        except Exception as e:
            Log.error(self, "Error while installing packages, "
                      "apt-get exited with error")

    def install(self, packages):
        all_packages = ' '.join(packages)
        try:
            with open('/var/log/ee/ee.log', 'a') as f:
                proc = subprocess.Popen("DEBIAN_FRONTEND=noninteractive "
                                        "apt-get install -o "
                                        "Dpkg::Options::=\"--force-confdef\""
                                        " -o "
                                        "Dpkg::Options::=\"--force-confold\""
                                        " -y --allow-unauthenticated {0}"
                                        .format(all_packages), shell=True,
                                        stdin=None, stdout=f, stderr=f,
                                        executable="/bin/bash")
                proc.wait()

            if proc.returncode == 0:
                return True
            else:
                Log.info(self, Log.FAIL + "Oops Something went "
                         "wrong!!")
                Log.error(self, "Check logs for reason "
                          "`tail /var/log/ee/ee.log` & Try Again!!!")

        except Exception as e:
            Log.info(self, Log.FAIL + "Oops Something went "
                     "wrong!!")
            Log.error(self, "Check logs for reason "
                      "`tail /var/log/ee/ee.log` & Try Again!!!")

    def remove(self, packages, auto=False, purge=False):
        all_packages = ' '.join(packages)
        try:
            with open('/var/log/ee/ee.log', 'a') as f:
                if purge:
                    proc = subprocess.Popen('apt-get purge -y {0}'
                                            .format(all_packages), shell=True,
                                            stdin=None, stdout=f, stderr=f,
                                            executable="/bin/bash")
                else:
                    proc = subprocess.Popen('apt-get remove -y {0}'
                                            .format(all_packages), shell=True,
                                            stdin=None, stdout=f, stderr=f,
                                            executable="/bin/bash")
                proc.wait()
            if proc.returncode == 0:
                return True
            else:
                Log.info(self, Log.FAIL + "Oops Something went "
                         "wrong!!")
                Log.error(self, "Check logs for reason "
                          "`tail /var/log/ee/ee.log` & Try Again!!!")

        except Exception as e:
            Log.error(self, "Error while installing packages, "
                      "apt-get exited with error")

    def auto_clean(self):
        """
        Similar to `apt-get autoclean`
        """
        try:
            orig_out = sys.stdout
            sys.stdout = open(self.app.config.get('log.logging', 'file'),
                              encoding='utf-8', mode='a')
            apt_get.autoclean("-y")
            sys.stdout = orig_out
        except ErrorReturnCode as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "Unable to apt-get autoclean")

    def auto_remove(self):
        """
        Similar to `apt-get autoremove`
        """
        try:
            Log.debug(self, "Running apt-get autoremove")
            apt_get.autoremove("-y")
        except ErrorReturnCode as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "Unable to apt-get autoremove")

    def is_installed(self, package_name):
        """
        Checks if package is available in cache and is installed or not
        returns True if installed otherwise returns False
        """
        apt_cache = apt.cache.Cache()
        apt_cache.open()
        if (package_name.strip() in apt_cache and
           apt_cache[package_name.strip()].is_installed):
            # apt_cache.close()
            return True
        # apt_cache.close()
        return False

    def download_only(self,package_name,repo_url=None,repo_key=None):
        """
        Similar to `apt-get install --download-only PACKAGE_NAME`
        """
        packages = ' '.join(package_name)
        try:
            with open('/var/log/ee/ee.log', 'a') as f:
                if repo_url is not None:
                    EERepo.add(self, repo_url=repo_url)
                if repo_key is not None:
                    EERepo.add_key(self, repo_key)
                proc = subprocess.Popen("apt-get update && DEBIAN_FRONTEND=noninteractive "
                                        "apt-get install -o "
                                        "Dpkg::Options::=\"--force-confdef\""
                                        " -o "
                                        "Dpkg::Options::=\"--force-confold\""
                                        " -y  --download-only {0}"
                                        .format(packages), shell=True,
                                        stdin=None, stdout=f, stderr=f,
                                        executable="/bin/bash")
                proc.wait()

            if proc.returncode == 0:
                return True
            else:
                Log.error(self,"Error in fetching dpkg package.\nReverting changes ..",False)
                if repo_url is not None:
                    EERepo.remove(self, repo_url=repo_url)
                return False
        except Exception as e:
            Log.error(self, "Error while downloading packages, "
                      "apt-get exited with error")

