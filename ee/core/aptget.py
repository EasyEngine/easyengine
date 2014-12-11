"""EasyEngine package installation using apt-get module."""
import apt
import sys


class EEAptGet:
    """Generic apt-get intialisation"""

    def __init__(self):
        self.cache = apt.Cache()
        self.fprogress = apt.progress.text.AcquireProgress()
        self.iprogress = apt.progress.base.InstallProgress()

    def update(self):
        """Similar to apt-get update"""
        self.cache.update(self.fprogress)
        pass

    def install(self, packages):
        """Installation of packages"""
        my_selected_packages = []
        # Cache Initialization
        self.cache = apt.Cache()
        # Cache Read
        self.cache.open()
        for package in packages:
            pkg = self.cache[package]
            # Check Package Installed
            if pkg.is_installed:
                # Check Package is Upgradeble
                if pkg.is_upgradable:
                    print("latest version of \'{package_name}\' available."
                          .format(package_name=pkg.installed))
                else:
                    print("\'{package_name}-{package_ver.version}\'"
                          "already the newest version"
                          .format(package_name=pkg.name,
                                  package_ver=pkg.installed))
            else:
                with self.cache.actiongroup():
                    # Mark Package for Installation
                    pkg.mark_install()
                my_selected_packages.append(pkg.name)

        # Check if packages available for install.
        if self.cache.install_count > 0:
            print("The following NEW packages will be installed:"
                  "\n {pkg_name}"
                  .format(pkg_name=my_selected_packages))
            print("{pkg_install_count} newly installed."
                  .format(pkg_install_count=self.cache.install_count))
            print("Need to get {req_download} bytes of archives"
                  .format(req_download=self.cache.required_download))
            print("After this operation, {space} bytes of"
                  "additional disk space will be used."
                  .format(space=self.cache.required_space))
            try:
                # Commit changes in cache (actually install)
                self.cache.commit(self.fprogress, self.iprogress)
            except Exception as e:
                print("package installation failed[{err}]"
                      .format(err=str(e)))

    def remove(self, packages, purge_value=False):
        """Removal of packages Similar to apt-get remove"""
        self.__init__()
        my_selected_packages = []
        # apt cache Initialization
        self.cache = apt.Cache()
        # Read cache i.e package list
        self.cache.open()
        for package in packages:
            pkg = self.cache[package]
            # Check if packages installed
            if pkg.is_installed:
                with self.cache.actiongroup():
                    # Mark packages for delete
                    # Mark to purge package if purge_value is True
                    pkg.mark_delete(purge=purge_value)
                my_selected_packages.append(pkg.name)
            else:
                print("Package '{package_name}' is not installed,"
                      " so not removed."
                      .format(package_name=packages))

        # Check if packages available for remove/update.
        if self.cache.delete_count > 0:
            print("The following NEW packages will be REMOVED:"
                  "\n {pkg_name}"
                  .format(pkg_name=my_selected_packages))
            print("{pkg_remove_count} to remove."
                  .format(pkg_remove_count=self.cache.delete_count))
            print("After this operation, {space} bytes disk spac"
                  "e will be freed.".format(space=self.cache.required_space))
            try:
                self.cache.commit()
            except Exception as e:
                print("Sorry, package installation failed [{err}]"
                      .format(err=str(e)))

    def purge(self, packages):
        """Purging of packages similar to apt-get purge"""
        self.remove(packages, purge_value=True)
