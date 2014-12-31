"""EasyEngine file utils core classes."""
import shutil
import os
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
                    self.app.log.error('Unable to Remove file'
                                       + os.path.basename(file)+e.reason())
                    self.app.log.info("Unable to remove file, [{err}]"
                                      .format(err=str(e.reason)))
                    return False

    def create_symlink(paths):
        src = paths[0]
        dst = paths[1]
        try:
            os.symlink(src, dst)
        except Exception as e:
            print("Unable to create symbolic link ...\n {0} "
                  .format(e.reason))

    def remove_symlink(filepath):
        try:
            os.unlink(path)
        except Exception as e:
            print("Unable to reomove symbolic link ...\n {0} "
                  .format(e.reason))
