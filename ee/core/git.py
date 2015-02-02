"""EasyEngine GIT module"""
from sh import git, ErrorReturnCode
from ee.core.logging import Log
import os


class EEGit:
    """Intialization of core variables"""
    def ___init__():
        # TODO method for core variables
        pass

    def add(self, paths, msg="Intializating"):
        """
            Initializes Directory as repository if not already git repo.
            and adds uncommited changes automatically
        """
        for path in paths:
            global git
            git = git.bake("--git-dir={0}/.git".format(path),
                           "--work-tree={0}".format(path))
            if os.path.isdir(path):
                if not os.path.isdir(path+"/.git"):
                    try:
                        Log.debug(self, "EEGit: git init at {0}"
                                  .format(path))
                        git.init(path)
                    except ErrorReturnCode as e:
                        Log.debug(self, "{0}".format(e))
                        Log.error(self, "Unable to git init at {0}"
                                  .format(path))
                status = git.status("-s")
                if len(status.splitlines()) > 0:
                    try:
                        Log.debug(self, "EEGit: git commit at {0}"
                                  .format(path))
                        git.add("--all")
                        git.commit("-am {0}".format(msg))
                    except ErrorReturnCode as e:
                        Log.debug(self, "{0}".format(e))
                        Log.error(self, "Unable to git commit at {0} "
                                  .format(path))
            else:
                Log.debug(self, "EEGit: Path {0} not present".format(path))

    def checkfilestatus(self, repo, filepath):
        """
            Checks status of file, If its tracked or untracked.
        """
        global git
        git = git.bake("--git-dir={0}/.git".format(repo),
                       "--work-tree={0}".format(repo))
        status = git.status("-s", "{0}".format(filepath))
        if len(status.splitlines()) > 0:
            return True
        else:
            return False
