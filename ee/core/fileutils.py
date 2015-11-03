"""EasyEngine file utils core classes."""
import shutil
import os
import sys
import glob
import shutil
import pwd
import fileinput
from ee.core.logging import Log


class EEFileUtils():
    """Utilities to operate on files"""
    def __init__():
        pass

    def remove(self, filelist):
        """remove files from given path"""
        for file in filelist:
            if os.path.isfile(file):
                Log.info(self, "Removing {0:65}".format(file), end=' ')
                os.remove(file)
                Log.info(self, "{0}".format("[" + Log.ENDC + "Done" +
                         Log.OKBLUE + "]"))
                Log.debug(self, 'file Removed')
            if os.path.isdir(file):
                try:
                    Log.info(self, "Removing {0:65}".format(file), end=' ')
                    shutil.rmtree(file)
                    Log.info(self, "{0}".format("[" + Log.ENDC + "Done" +
                             Log.OKBLUE + "]"))
                except shutil.Error as e:
                    Log.debug(self, "{err}".format(err=str(e.reason)))
                    Log.error(self, 'Unable to Remove file ')

    def create_symlink(self, paths, errormsg=''):
        """
        Create symbolic links provided in list with first as source
        and second as destination
        """
        src = paths[0]
        dst = paths[1]
        if not os.path.islink(dst):
            try:
                Log.debug(self, "Creating Symbolic link, Source:{0}, Dest:{1}"
                          .format(src, dst))
                os.symlink(src, dst)
            except Exception as e:
                Log.debug(self, "{0}{1}".format(e.errno, e.strerror))
                Log.error(self, "Unable to create symbolic link ...\n ")
        else:
            Log.debug(self, "Destination: {0} exists".format(dst))

    def remove_symlink(self, filepath):
        """
            Removes symbolic link for the path provided with filepath
        """
        try:
            Log.debug(self, "Removing symbolic link: {0}".format(filepath))
            os.unlink(filepath)
        except Exception as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "Unable to reomove symbolic link ...\n")

    def copyfiles(self, src, dest):
        """
        Copies files:
            src : source path
            dest : destination path

            Recursively copy an entire directory tree rooted at src.
            The destination directory, named by dst, must not already exist;
            it will be created as well as missing parent directories.
        """
        try:
            Log.debug(self, "Copying files, Source:{0}, Dest:{1}"
                      .format(src, dest))
            shutil.copytree(src, dest)
        except shutil.Error as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, 'Unable to copy files from {0} to {1}'
                      .format(src, dest))
        except IOError as e:
            Log.debug(self, "{0}".format(e.strerror))
            Log.error(self, "Unable to copy files from {0} to {1}"
                      .format(src, dest))

    def copyfile(self, src, dest):
        """
        Copy file:
            src : source path
            dest : destination path
        """
        try:
            Log.debug(self, "Copying file, Source:{0}, Dest:{1}"
                      .format(src, dest))
            shutil.copy2(src, dest)
        except shutil.Error as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, 'Unable to copy file from {0} to {1}'
                      .format(src, dest))
        except IOError as e:
            Log.debug(self, "{0}".format(e.strerror))
            Log.error(self, "Unable to copy file from {0} to {1}"
                      .format(src, dest))

    def searchreplace(self, fnm, sstr, rstr):
        """
            Search replace strings in file
            fnm : filename
            sstr: search string
            rstr: replace string
        """
        try:
            Log.debug(self, "Doning search and replace, File:{0},"
                      "Source string:{1}, Dest String:{2}"
                      .format(fnm, sstr, rstr))
            for line in fileinput.input(fnm, inplace=True):
                print(line.replace(sstr, rstr), end='')
            fileinput.close()
        except Exception as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "Unable to search {0} and replace {1} {2}"
                      .format(fnm, sstr, rstr))

    def mvfile(self, src, dst):
        """
            Moves file from source path to destination path
            src : source path
            dst : Destination path
        """
        try:
            Log.debug(self, "Moving file from {0} to {1}".format(src, dst))
            shutil.move(src, dst)
        except Exception as e:
            Log.debug(self, "{err}".format(err=e))
            Log.error(self, 'Unable to move file from {0} to {1}'
                      .format(src, dst))

    def chdir(self, path):
        """
            Change Directory to path specified
            Path : path for destination directory
        """
        try:
            Log.debug(self, "Changing directory to {0}"
                      .format(path))
            os.chdir(path)
        except OSError as e:
            Log.debug(self, "{err}".format(err=e.strerror))
            Log.error(self, 'Unable to Change Directory {0}'.format(path))

    def chown(self, path, user, group, recursive=False):
        """
            Change Owner for files
            change owner for file with path specified
            user: username of owner
            group: group of owner
            recursive: if recursive is True change owner for all
                       files in directory
        """
        userid = pwd.getpwnam(user)[2]
        groupid = pwd.getpwnam(user)[3]
        try:
            Log.debug(self, "Changing ownership of {0}, Userid:{1},Groupid:{2}"
                      .format(path, userid, groupid))
            # Change inside files/directory permissions only if recursive flag
            # is set
            if recursive:
                for root, dirs, files in os.walk(path):
                    for d in dirs:
                        os.chown(os.path.join(root, d), userid,
                                 groupid)
                    for f in files:
                        os.chown(os.path.join(root, f), userid,
                                 groupid)
            os.chown(path, userid, groupid)
        except shutil.Error as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "Unable to change owner : {0}".format(path))
        except Exception as e:
            Log.debug(self, "{0}".format(e))
            Log.error(self, "Unable to change owner : {0} ".format(path))

    def chmod(self, path, perm, recursive=False):
        """
            Changes Permission for files
            path : file path permission to be changed
            perm : permissions to be given
            recursive: change permission recursively for all files
        """
        try:
            Log.debug(self, "Changing permission of {0}, Perm:{1}"
                      .format(path, perm))
            if recursive:
                for root, dirs, files in os.walk(path):
                    for d in dirs:
                        os.chmod(os.path.join(root, d), perm)
                    for f in files:
                        os.chmod(os.path.join(root, f), perm)
            else:
                os.chmod(path, perm)
        except OSError as e:
            Log.debug(self, "{0}".format(e.strerror))
            Log.error(self, "Unable to change owner : {0}".format(path))

    def mkdir(self, path):
        """
            create directories.
            path : path for directory to be created
            Similar to `mkdir -p`
        """
        try:
            Log.debug(self, "Creating directories: {0}"
                      .format(path))
            os.makedirs(path)
        except OSError as e:
            Log.debug(self, "{0}".format(e.strerror))
            Log.error(self, "Unable to create directory {0} ".format(path))

    def isexist(self, path):
        """
            Check if file exist on given path
        """
        try:
            if os.path.exists(path):
                return (True)
            else:
                return (False)
        except OSError as e:
            Log.debug(self, "{0}".format(e.strerror))
            Log.error(self, "Unable to check path {0}".format(path))

    def grep(self, fnm, sstr):
        """
            Searches for string in file and returns the matched line.
        """
        try:
            Log.debug(self, "Finding string {0} to file {1}"
                      .format(sstr, fnm))
            for line in open(fnm, encoding='utf-8'):
                if sstr in line:
                    return line
            return False
        except OSError as e:
            Log.debug(self, "{0}".format(e.strerror))
            Log.error(self, "Unable to Search string {0} in {1}"
                      .format(sstr, fnm))

    def rm(self, path):
        """
            Remove files
        """
        Log.debug(self, "Removing {0}".format(path))
        if EEFileUtils.isexist(self, path):
            try:
                if os.path.isdir(path):
                    shutil.rmtree(path)
                else:
                    os.remove(path)
            except shutil.Error as e:
                Log.debug(self, "{0}".format(e))
                Log.error(self, "Unable to remove directory : {0} "
                          .format(path))
            except OSError as e:
                Log.debug(self, "{0}".format(e))
                Log.error(self, "Unable to remove file  : {0} "
                          .format(path))
