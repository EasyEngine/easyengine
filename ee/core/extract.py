"""EasyEngine extarct core classes."""
import tarfile
import os


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
            self.app.log.error('Unable to extract file', e.reason())
            self.app.log.info("Unable to extract file "+file)
            return False