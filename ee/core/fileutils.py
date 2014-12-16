"""EasyEngine file utils core classes."""
import shutil
import os


class EEFileUtils():
    """Method to operate on files"""
    def __init__():
        pass

    def remove(filelist):
        for file in filelist:
                if os.path.isfile(file):
                    print("Removing "+os.path.basename(file)+" ...")
                    os.remove(file)
                    print("Done")
                if os.path.isdir(file):
                    try:
                        print("Removing "+os.path.basename(file)+" ...")
                        shutil.rmtree(file)
                        print("Done")
                    except shutil.Error as e:
                        print("Unable to remove file, [{err}]"
                              .format(err=str(e.reason)))
                        return False
