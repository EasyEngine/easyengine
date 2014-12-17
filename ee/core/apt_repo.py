import os.path
from ee.core.shellexec import EEShellExec
from ee.core.variables import EEVariables


class EERepo():
    """Manage Repositories"""

    def __init__(self):
        """Initialize """
        pass

    def add(repo_url=None, ppa=None):
        # TODO add repository code

        if repo_url is not None:
            repo_file_path = ("/etc/apt/sources.list.d/"
                              + EEVariables().ee_repo_file)
            try:
                with open(repo_file_path, "a") as repofile:
                    repofile.write(repo_url)
                    repofile.close()
                return True
            except IOError as e:
                print("File I/O error({0}): {1}".format(e.errno, e.strerror))
            except Exception as e:
                print("{error}".format(error=e))
                return False
        if ppa is not None:
            if EEVariables.ee_platform_distro == 'squeeze':
                print("Cannot add repo for {distro}"
                      .format(distro=EEVariables.ee_platform_distro))
            else:
                EEShellExec.cmd_exec("add-apt-repository -y '{ppa_name}'"
                                     .format(ppa_name=ppa))

    def remove(repo_url=None):
        # TODO remove repository
        EEShellExec.cmd_exec("add-apt-repository -y "
                             "--remove '{ppa_name}'".format(ppa_name=repo_url))
        pass

    def add_key(keyserver, keyids):
        EEShellExec.cmd_exec("gpg --keyserver {serv}".format(serv=keyserver)
                             + " --recv-keys {key}".format(key=keyids))
        EEShellExec.cmd_exec("gpg -a --export --armor {0}".format(keyids)
                             + " | apt-key add - ")

# if __name__ == '__main__':
#   EERepo().add(repo_url="http://ds.asf", codename="trusty", repo_type="main")
