"""EasyEngine package installation using apt-get module."""
import apt
import sys


class EEAptGet:
    """Generice apt-get intialisation"""

    def __init__(self):
        self.cache = apt.cache.Cache()

    """Installation of packages"""
    def update(self):
        self.cache.update()
        pass

    """Installation of packages"""
    def install(self, packages):
        pkg = self.cache[packages]
        if pkg.is_installed:
            print("pkg already installed")
        else:
            pkg.mark_install()
            try:
                self.cache.commit(apt.progress.TextFetchProgress(),
                                  apt.progress.InstallProgress())

            except Exception as e:
                print("Sorry, package installation failed [{err}]"
                      .format(err=str(e)))

    """Removal of packages"""
    def remove(self, packages):
        pkg = self.cache[packages]
        if pkg.is_installed:
            pkg.mark_delete()
            try:
                self.cache.commit()

            except Exception as e:
                print("Sorry, package installation failed [{err}]"
                      .format(err=str(e)))

        else:
            print("pkg not installed")

    """Purging of packages"""
    def purge():
        # TODO Method to purge packages
        pass
