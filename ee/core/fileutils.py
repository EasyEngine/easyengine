"""EasyEngine file utils core classes."""
import shutil
import os


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
