"""EasyEngine package installation using apt-get module."""
import apt
import apt_pkg
import sys
from ee.core.logging import Log


class EEAptGet():
    """Generic apt-get intialisation"""

    def update(self):
        """Similar to apt-get update"""

        # app.log.debug("Update cache")
        cache = apt.Cache()
        fprogress = apt.progress.text.AcquireProgress()
        iprogress = apt.progress.base.InstallProgress()
        cache.update()
        cache.close()

    def upgrade(self, packages):
        """Similar to apt-get update"""
        cache = apt.Cache()
        fprogress = apt.progress.text.AcquireProgress()
        iprogress = apt.progress.base.InstallProgress()
        my_selected_packages = []
        # Cache Initialization
        if not cache:
            cache = apt.Cache()
        # Cache Read
        cache.open()
        for package in packages:
            pkg = cache[package]
            # Check Package Installed
            if pkg.is_installed:
                # Check Package is Upgradeble
                if pkg.is_upgradable:
                    with cache.actiongroup():
                        # Mark Package for Upgrade
                        pkg.mark_upgrade()
                    my_selected_packages.append(pkg.installed)
                else:
                    print("\'{package_name}-{package_ver.version}\'"
                          "already the newest version"
                          .format(package_name=pkg.name,
                                  package_ver=pkg.installed))

        # Check if packages available for install.
        if len(my_selected_packages) > 0:
            print("The following packages will be upgraded:"
                  "\n {pkg_name}"
                  .format(pkg_name=my_selected_packages))
            print("{pkg_install_count} newly installed."
                  .format(pkg_upgrade_count=len(my_selected_packages)))
            print("Need to get {req_download} bytes of archives"
                  .format(req_download=cache.required_download))
            print("After this operation, {space} bytes of"
                  "additional disk space will be used."
                  .format(space=cache.required_space))
            try:
                # Commit changes in cache (actually install)
                cache.commit(fprogress, iprogres)
            except Exception as e:
                print("package installation failed. [{err}]"
                      .format(err=str(e)))
                return(False)
        return(True)

    def install(self, packages):
        """Installation of packages"""
        cache = apt.Cache()
        fprogress = apt.progress.text.AcquireProgress()
        iprogress = apt.progress.base.InstallProgress()
        my_selected_packages = []
        # Cache Initialization
        if not cache:
            cache = apt.Cache()
        # Cache Read
        cache.open()

        for package in packages:
            try:
                pkg = cache[package]
            except KeyError as e:
                continue
            # Check Package Installed
            if pkg.is_installed or pkg.marked_install:
                # Check Package is Upgradeble
                if pkg.is_upgradable:
                    print("latest version of \'{package_name}\' available."
                          .format(package_name=pkg.installed))
                else:
                    # Check if package already marked for install
                    if not pkg.marked_install:
                        print("\'{package_name}-{package_ver}\'"
                              "already the newest version"
                              .format(package_name=pkg.shortname,
                                      package_ver=pkg.installed))
            else:
                with cache.actiongroup():
                    # Mark Package for Installation
                    pkg.mark_install()
                my_selected_packages.append(pkg.name)

        # Check if packages available for install.
        if cache.install_count > 0:
            print("The following NEW packages will be installed:"
                  "\n {pkg_name}"
                  .format(pkg_name=my_selected_packages))
            print("{pkg_install_count} newly installed."
                  .format(pkg_install_count=cache.install_count))
            print("Need to get {req_download} bytes of archives"
                  .format(req_download=cache.required_download))
            print("After this operation, {space:.2f} MB of"
                  " additional disk space will be used."
                  .format(space=cache.required_space/1e6))
            try:
                # Commit changes in cache (actually install)
                cache.commit()
            except Exception as e:
                print("package installation failed. [{err}]"
                      .format(err=str(e)))
                return(False)
                cache.close()
        cache.close()
        return(True)

    def remove(self, packages, auto=False, purge=False):
        def __dependencies_loop(cache, deplist, pkg, onelevel=True):
            """ Loops through pkg's dependencies.
            Returns a list with every package found. """
            if onelevel:
                onelevellist = []
            if not pkg.is_installed:
                return
            for depf in pkg.installed.dependencies:
                for dep in depf:
                    # if (dep.name in cache and not cache[dep.name]
                    #    in deplist):
                    #     deplist.append(cache[dep.name])
                    #     __dependencies_loop(cache, deplist, cache[dep.name])
                    # if onelevel:
                    if dep.name in cache:
                        if (cache[dep.name].is_installed and
                           cache[dep.name].is_auto_installed):
                            onelevellist.append(cache[dep.name])
            # if onelevel:
            return onelevellist

        cache = apt.Cache()
        fprogress = apt.progress.text.AcquireProgress()
        iprogress = apt.progress.base.InstallProgress()

        onelevel = []

        my_selected_packages = []
        # Cache Initialization
        if not cache:
            cache = apt.Cache()
        # Cache Read
        cache.open()
        for package in packages:
            print("processing", package)
            try:
                pkg = cache[package]
            except KeyError as e:
                Log.debug(self, "{0}".format(e))
                continue
            if not pkg.is_installed:
                Log.info(self, "Package '{package_name}' is not installed,"
                         " so not removed."
                         .format(package_name=pkg.name))
                continue
            my_selected_packages.append(pkg.name)
            # How logic works:
            # 1) We loop trough dependencies's dependencies and add them to
            # the list.
            # 2) We sequentially remove every package in list
            # - via is_auto_installed we check if we can safely remove it
            deplist = []
            onelevel = onelevel + __dependencies_loop(cache, deplist, pkg,
                                                      onelevel=True)
            # Mark for deletion the first package, to fire up
            # auto_removable Purge?

            try:
                if purge:
                    pkg.mark_delete(purge=True)
                else:
                    pkg.mark_delete(purge=False)
            except SystemError as e:
                Log.debug(self, "{0}".format(e))
                apt.ProblemResolver(cache).remove(pkg)
                # print(pkg.inst_state)
                # Log.error(self, "Unable to purge packages.")

        for dep in onelevel:
            my_selected_packages.append(dep.name)
            try:
                if purge:
                    dep.mark_delete(purge=True)
                else:
                    dep.mark_delete(purge=False)
            except SystemError as e:
                Log.debug(self, "{0}".format(e))
                Log.error(self, "Unable to purge depedencies.")

        # Check if packages available for remove/update.
        if cache.delete_count > 0:
            # app.log.debug('packages will be REMOVED ')
            print("The following packages will be REMOVED:"
                  "\n {pkg_name}"
                  .format(pkg_name=my_selected_packages))
            print("{pkg_remove_count} to remove."
                  .format(pkg_remove_count=cache.delete_count))
            # app.log.debug('bytes disk space will be freed')
            print("After this operation, {space:.2f} MB disk space "
                  "will be freed.".format(space=cache.required_space/1e6))
            try:
                cache.commit(fprogress, iprogress)
            except Exception as e:
                # app.log.error('Sorry, package installation failed ')
                print("Sorry, package installation failed [{err}]"
                      .format(err=str(e)))
                cache.close()
                return(False)
        cache.close()
        return(True)

    def is_installed(self, package):
        cache = apt.Cache()
        fprogress = apt.progress.text.AcquireProgress()
        iprogress = apt.progress.base.InstallProgress()

        # Cache Initialization
        if not cache:
            cache = apt.Cache()
        # Cache Read
        cache.open()
        try:
            pkg = cache[package]
            # Check Package Installed
            if pkg.is_installed:
                cache.close()
                return True
            else:
                cache.close()
                return False
        except KeyError as e:
            Log.debug(self, "{0}".format(e))
        except Exception as e:
            cache.close()
            return False
