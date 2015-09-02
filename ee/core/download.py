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
        """Download packages, packges must be list in format of
        [url, path, package name]"""
        for package in packages:
            url = package[0]
            filename = package[1]
            pkg_name = package[2]
            try:
                directory = os.path.dirname(filename)
                if not os.path.exists(directory):
                    os.makedirs(directory)
                Log.info(self, "Downloading {0:20}".format(pkg_name), end=' ')
                urllib.request.urlretrieve(url, filename)
                Log.info(self, "{0}".format("[" + Log.ENDC + "Done"
                                            + Log.OKBLUE + "]"))
            except urllib.error.URLError as e:
                Log.debug(self, "[{err}]".format(err=str(e.reason)))
                Log.error(self, "Unable to download file, {0}"
                          .format(filename))
                return False
            except urllib.error.HTTPError as e:
                Log.error(self, "Package download failed. {0}"
                          .format(pkg_name))
                Log.debug(self, "[{err}]".format(err=str(e.reason)))
                return False
            except urllib.error.ContentTooShortError as e:
                Log.debug(self, "{0}{1}".format(e.errno, e.strerror))
                Log.error(self, "Package download failed. The amount of the"
                          " downloaded data is less than "
                          "the expected amount \{0} ".format(pkg_name))
                return False
