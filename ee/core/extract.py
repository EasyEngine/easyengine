"""EasyEngine extarct core classes."""
import tarfile
import os
from ee.core.logging import Log


class EEExtract():
    """Method to extract from tar.gz file"""

    def extract(self, file, path):
        try:
            tar = tarfile.open(file)
            tar.extractall(path=path)
            tar.close()
            os.remove(file)
            return True
        except tarfile.TarError as e:
            Log.error(self, 'Unable to extract file \{0} {1}{2}'
                      .format(e.errno, e.strerror, "[FAIL]"))
            return False
