"""EasyEngine package installation using apt-get module."""
import apt
import apt_pkg
import sys
from ee.core.logging import Log
from sh import apt_get


class EEAptGet():
    """Generic apt-get intialisation"""

    def update(self):
        try:
            apt_cache = apt.cache.Cache()
            apt_cache.update()
            success = (apt_cache.commit(
                       apt.progress.text.AcquireProgress(),
                       apt.progress.base.InstallProgress()))
            #apt_cache.close()
            return success
        except AttributeError as e:
            Log.error(self, 'AttributeError: ' + str(e))

    def dist_upgrade():
        apt_cache = apt.cache.Cache()
        apt_cache.update()
        apt_cache.open(None)
        apt_cache.upgrade(True)
        success = (apt_cache.commit(
                   apt.progress.text.AcquireProgress(),
                   apt.progress.base.InstallProgress()))
        #apt_cache.close()
        return success

    def install(self, packages):
        """Installation of packages"""
        def install_package(self, package_name):
            apt_pkg.init()
            # #apt_pkg.PkgSystemLock()
            apt_cache = apt.cache.Cache()
            pkg = apt_cache[package_name.strip()]
            if package_name.strip() in apt_cache:
                if pkg.is_installed:
                    #apt_pkg.PkgSystemUnLock()
                    Log.info(self, 'Trying to install a package that '
                             'is already installed (' +
                             package_name.strip() + ')')
                    #apt_cache.close()
                    return False
                else:
                    pkg.mark_install()
                    try:
                        #apt_pkg.PkgSystemUnLock()
                        result = apt_cache.commit()
                        #apt_cache.close()
                        return result
                    except SystemError as e:
                        Log.error(self, 'SystemError: ' + str(e))
                        #apt_cache.close()
            else:
                #apt_cache.close()
                Log.error(self, 'Unknown package selected (' +
                          package_name.strip() + ')')

        for package in packages:
            if not install_package(self, package):
                continue

    def remove(self, packages, auto=False, purge=False):
        def remove_package(self, package_name, purge=False):
            apt_pkg.init()
            #apt_pkg.PkgSystemLock()
            apt_cache = apt.cache.Cache()
            pkg = apt_cache[package_name.strip()]
            if package_name.strip() in apt_cache:
                if not pkg.is_installed:
                    #apt_pkg.PkgSystemUnLock()
                    Log.info(self, 'Trying to uninstall a package '
                             'that is not installed (' +
                             package_name.strip() + ')')
                    return False
                else:
                    try:
                        pkg.mark_delete(purge)
                    except SystemError as e:
                        Log.debug(self, 'SystemError: ' + str(e))
                        return False
                    try:
                        #apt_pkg.PkgSystemUnLock()
                        result = apt_cache.commit()
                        #apt_cache.close()
                        return result
                    except SystemError as e:
                        Log.debug(self, 'SystemError: ' + str(e))
                        return False
                        #apt_cache.close()
            else:
                #apt_cache.close()
                Log.error(self, 'Unknown package selected (' +
                          package_name.strip() + ')')

        for package in packages:
            if not remove_package(self, package, purge=purge):
                continue

    def auto_clean(self):
        try:
            apt_get.autoclean("-y")
        except ErrorReturnCode as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "Unable to apt-get autoclean")

    def auto_remove(self):
        try:
            Log.debug(self, "Running apt-get autoremove")
            apt_get.autoremove("-y")
        except ErrorReturnCode as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "Unable to apt-get autoremove")

    def is_installed(self, package_name):
        apt_cache = apt.cache.Cache()
        apt_cache.open()
        if (package_name.strip() in apt_cache and
           apt_cache[package_name.strip()].is_installed):
            apt_cache.close()
            return True
        #apt_cache.close()
        return False
