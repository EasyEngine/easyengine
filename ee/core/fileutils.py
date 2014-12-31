"""EasyEngine file utils core classes."""
import shutil
import os
import sys
import glob


class EEFileUtils():
    """Method to operate on files"""
    def __init__():
        pass

    def remove(self, filelist):
        for file in filelist:
            if os.path.isfile(file):
                self.app.log.debug('Removing file')
                self.app.log.info("Removing "+os.path.basename(file)+" ...")
                os.remove(file)
                self.app.log.debug('file Removed')
                self.app.log.info("Done")
            if os.path.isdir(file):
                try:
                    self.app.log.debug('Removing file')
                    print("Removing "+os.path.basename(file)+" ...")
                    shutil.rmtree(file)
                    self.app.log.info("Done")
                except shutil.Error as e:
                    self.app.log.error('Unable to Remove file {err}'
                                       .format(err=str(e.reason)))
                    sys.exit(1)

    def create_symlink(self, paths):
        src = paths[0]
        dst = paths[1]
        try:
            os.symlink(src, dst)
        except Exception as e:
            self.app.log.error("Unable to create symbolic link ...\n {0} {1}"
                               .format(e.errno, e.strerror))
            sys.exit(1)

    def remove_symlink(self, filepath):
        try:
            os.unlink(filepath)
        except Exception as e:
            self.app.log.error("Unable to reomove symbolic link ...\n {0} {1}"
                               .format(e.errno, e.strerror))
            sys.exit(1)
