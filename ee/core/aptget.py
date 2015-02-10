"""EasyEngine package installation using apt-get module."""
import apt
import apt_pkg
import sys
from ee.core.logging import Log
from sh import apt_get


class EEAptGet():
    """Generic apt-get intialisation"""

    def update(self):
        """
        Similar to `apt-get upgrade`
        """
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
        except FetchFailedException as e:
            Log.debug(self, 'SystemError:  ' + str(e))
            Log.error(self, 'Unable to Fetch update')

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
            #apt_cache.close()
            return success
        except AttributeError as e:
            Log.error(self, 'AttributeError: ' + str(e))
        except FetchFailedException as e:
            Log.debug(self, 'SystemError:  ' + str(e))
            Log.error(self, 'Unable to Fetch update')

    def install(self, packages):
        """
        Similar to `apt-get install`
        """
        apt_pkg.init()
        # #apt_pkg.PkgSystemLock()
        global apt_cache
        apt_cache = apt.cache.Cache()

        def install_package(self, package_name):
            pkg = apt_cache[package_name.strip()]
            if package_name.strip() in apt_cache:
                if pkg.is_installed:
                    #apt_pkg.PkgSystemUnLock()
                    Log.debug(self, 'Trying to install a package that '
                              'is already installed (' +
                              package_name.strip() + ')')
                    #apt_cache.close()
                    return False
                else:
                    try:
                        # print(pkg.name)
                        pkg.mark_install()
                    except Exception as e:
                        Log.debug(self, str(e))
                        Log.error(self, str(e))
            else:
                #apt_cache.close()
                Log.error(self, 'Unknown package selected (' +
                          package_name.strip() + ')')

        for package in packages:
            if not install_package(self, package):
                continue

        if apt_cache.install_count > 0:
            try:
                #apt_pkg.PkgSystemUnLock()
                result = apt_cache.commit()
                #apt_cache.close()
                return result
            except SystemError as e:
                Log.debug(self, 'SystemError: ' + str(e))
                Log.error(self, 'SystemError: ' + str(e))
                #apt_cache.close()
            except Exception as e:
                Log.debug(self, str(e))
                Log.error(self, str(e))

    def remove(self, packages, auto=False, purge=False):
        """
            Similar to `apt-get remove/purge`
            purge packages if purge=True
        """
        apt_pkg.init()
        # apt_pkg.PkgSystemLock()
        global apt_cache
        apt_cache = apt.cache.Cache()

        def remove_package(self, package_name, purge=False):
            pkg = apt_cache[package_name.strip()]
            if package_name.strip() in apt_cache:
                if not pkg.is_installed:
                    # apt_pkg.PkgSystemUnLock()
                    Log.debug(self, 'Trying to uninstall a package '
                              'that is not installed (' +
                              package_name.strip() + ')')
                    return False
                else:
                    try:
                        # print(pkg.name)
                        pkg.mark_delete(purge)
                    except SystemError as e:
                        Log.debug(self, 'SystemError: ' + str(e))
                        return False
            else:
                # apt_cache.close()
                Log.error(self, 'Unknown package selected (' +
                          package_name.strip() + ')')

        for package in packages:
            if not remove_package(self, package, purge=purge):
                continue

        if apt_cache.delete_count > 0:
            try:
                # apt_pkg.PkgSystemUnLock()
                result = apt_cache.commit()
                # apt_cache.close()
                return result
            except SystemError as e:
                Log.debug(self, 'SystemError: ' + str(e))
                return False
            except Exception as e:
                Log.debug(self, str(e))
                Log.error(self, str(e))
                # apt_cache.close()

    def auto_clean(self):
        """
        Similar to `apt-get autoclean`
        """
        try:
            apt_get.autoclean("-y")
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
