from sh import git, ErrorReturnCode
import os


class EEGit():
    """Intialization of core variables"""
    def ___init__():
        # TODO method for core variables
        pass

    def add(paths, msg="Intializating"):
        for path in paths:
            agit = git.bake("--git-dir={0}/.git".format(path),
                            "--work-tree={0}".format(path))
            if os.path.isdir(path):
                if not os.path.isdir(path+"/.git"):
                    try:
                        git.init(path)
                    except ErrorReturnCode as e:
                        print(e)
                        sys.exit(1)
                status = git.status("-s")
                if len(status.splitlines()) > 0:
                    try:
                        git.add("--all")
                        git.commit("-am {0}".format(msg))
                    except ErrorReturnCode as e:
                        print(e)
                        sys.exit(1)
        pass
