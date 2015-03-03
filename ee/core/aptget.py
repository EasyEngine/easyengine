"""EasyEngine package installation using apt-get module."""
import apt
import apt_pkg
import sys
from ee.core.logging import Log
from sh import apt_get
from sh import ErrorReturnCode


class EEAptGet():
    """Generic apt-get intialisation"""

    def update(self):
        """
        Similar to `apt-get upgrade`
        """
        global apt_get
        apt_get = apt_get.bake("-y")
        try:
            for line in apt_get.update(_iter=True):
                Log.info(self, Log.ENDC+line+Log.OKBLUE, end=' ')
        except ErrorReturnCode as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "Unable to run apt-get update")

    def dist_upgrade():
        """
        Similar to `apt-get upgrade`
        """
        try:
            apt_cache = apt.cache.Cache()
            apt_cache.update()
            apt_cache.open(None)
            apt_cache.upgrade(True)
            success = (apt_cache.commit(
                       apt.progress.text.AcquireProgress(),
                       apt.progress.base.InstallProgress()))
            # apt_cache.close()
            return success
        except AttributeError as e:
            Log.error(self, 'AttributeError: ' + str(e))
        except FetchFailedException as e:
            Log.debug(self, 'SystemError:  ' + str(e))
            Log.error(self, 'Unable to Fetch update')

    def install(self, packages):
        global apt_get
        apt_get = apt_get.bake("-y")
        try:
            for line in apt_get.install("-o",
                                        "Dpkg::Options::=--force-confold",
                                        *packages, _iter=True):
                Log.info(self, Log.ENDC+line+Log.OKBLUE, end=' ')
        except ErrorReturnCode as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "Unable to run apt-get install")

    def remove(self, packages, auto=False, purge=False):
        global apt_get
        apt_get = apt_get.bake("-y")
        try:
            if purge == "True":
                for line in apt_get.purge(*packages, _iter=True):
                    Log.info(self, Log.ENDC+line+Log.OKBLUE, end=' ')
            else:
                for line in apt_get.remove(*packages, _iter=True):
                    Log.info(self, Log.ENDC+line+Log.OKBLUE, end=' ')
        except ErrorReturnCode as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "Unable to remove packages")

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
