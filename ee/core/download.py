"""EasyEngine download core classes."""
import urllib.request
import urllib.error
import os


class EEDownload():
    """Method to download using urllib"""
    def __init__():
        pass

    def download(self, packages):
        for package in packages:
            url = package[0]
            filename = package[1]
            try:
                directory = os.path.dirname(filename)
                if not os.path.exists(directory):
                    os.makedirs(directory)
                self.app.log.info("Downloading "+os.path.basename(url)+" ...")
                urllib.request.urlretrieve(url, filename)
                self.app.log.info("Done")
            except urllib.error.URLError as e:
                self.app.log.error("Error is :"
                                   + os.path.basename(url)+e.reason())
                self.app.log.info("Unable to donwload file, [{err}]"
                                  .format(err=str(e.reason)))
                return False
            except urllib.error.HTTPError as e:
                self.app.log.error("Package download failed", e.reason())
                self.app.log.info("Package download failed. [{err}]"
                                  .format(err=str(e.reason)))
                return False
            except urllib.error.ContentTooShortError as e:
                self.app.log.error("Package download failed. The amount of the"
                                   " downloaded data is less than "
                                   "the expected amount"+e.reason())
                self.app.log.info("Package download failed. The amount of the"
                                  "downloaded data is less than"
                                  " the expected amount")
                return False
