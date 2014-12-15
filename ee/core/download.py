"""EasyEngine download core classes."""
import urllib.request
import urllib.error


class EEDownload():
    """Method to download using urllib"""
    def __init__():
        pass

    def download(url, filename):
        try:
            urllib.request.urlretrieve(url, filename)
            return True
        except urllib.error.URLError as e:
            print("Unable to donwload file, [{err}]".format(err=str(e.reason)))
            return False
        except urllib.error.HTTPError as e:
            print("Package download failed. [{err}]".format(err=str(e.reason)))
            return False
        except urllib.error.ContentTooShortError as e:
            print("Package download failed. The amount of the downloaded data"
                  "is less than the expected amount")
            return False
