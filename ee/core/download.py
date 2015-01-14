"""EasyEngine download core classes."""
import urllib.request
import urllib.error
import os
from ee.core.logging import Log


class EEDownload():
    """Method to download using urllib"""
    def __init__():
        pass

    def download(self, packages):
        for package in packages:
            url = package[0]
            filename = package[1]
            pkg_name = package[2]
            try:
                directory = os.path.dirname(filename)
                if not os.path.exists(directory):
                    os.makedirs(directory)
                Log.info(self, "Downloading "+pkg_name+" ...")
                urllib.request.urlretrieve(url, filename)
            except urllib.error.URLError as e:
                Log.error(self, "Unable to donwload file, {0}"
                          .format(filename))
                Log.debug(self, "[{err}]".format(err=str(e.reason)))
                return False
            except urllib.error.HTTPError as e:
                Log.error(self, "Package download failed. {0}"
                          .format(pkg_name))
                Log.debug(self, "[{err}]".format(err=str(e.reason)))
                return False
            except urllib.error.ContentTooShortError as e:
                Log.error(self, "Package download failed. The amount of the"
                          " downloaded data is less than "
                          "the expected amount \{0} ".format(pkg_name))
                Log.debug(self, "{0}{1}".format(e.errno, e.strerror))
                return False
