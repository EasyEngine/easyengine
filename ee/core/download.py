"""EasyEngine download core classes."""
import urllib.request
import urllib.error
import os


class EEDownload():
    """Method to download using urllib"""
    def __init__():
        pass

    def download(packages):
        for package in packages:
            url = package[0]
            filename = package[1]
            try:
                directory = os.path.dirname(filename)
                if not os.path.exists(directory):
                    os.makedirs(directory)
                urllib.request.urlretrieve(url, filename)
                return True
            except urllib.error.URLError as e:
                print("Unable to donwload file, [{err}]"
                      .format(err=str(e.reason)))
                return False
            except urllib.error.HTTPError as e:
                print("Package download failed. [{err}]"
                      .format(err=str(e.reason)))
                return False
            except urllib.error.ContentTooShortError as e:
                print("Package download failed. The amount of the"
                      "downloaded data is less than the expected amount")
                return False
