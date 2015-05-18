"""EasyEngine packages repository operations"""
from ee.core.shellexec import EEShellExec
from ee.core.variables import EEVariables
from ee.core.logging import Log
import os


class EERepo():
    """Manage Repositories"""

    def __init__(self):
        """Initialize """
        pass

    def add(self, repo_url=None, ppa=None):
        """
        This function used to add apt repositories and or ppa's
        If repo_url is provided adds repo file to
            /etc/apt/sources.list.d/
        If ppa is provided add apt-repository using
            add-apt-repository
        command.
        """

        if repo_url is not None:
            repo_file_path = ("/etc/apt/sources.list.d/"
                              + EEVariables().ee_repo_file)
            try:
                if not os.path.isfile(repo_file_path):
                    with open(repo_file_path,
                              encoding='utf-8', mode='a') as repofile:
                        repofile.write(repo_url)
                        repofile.write('\n')
                        repofile.close()
                elif repo_url not in open(repo_file_path,
                                          encoding='utf-8').read():
                    with open(repo_file_path,
                              encoding='utf-8', mode='a') as repofile:
                        repofile.write(repo_url)
                        repofile.write('\n')
                        repofile.close()
                return True
            except IOError as e:
                Log.debug(self, "{0}".format(e))
                Log.error(self, "File I/O error.")
            except Exception as e:
                Log.debug(self, "{0}".format(e))
                Log.error(self, "Unable to add repo")
        if ppa is not None:
            EEShellExec.cmd_exec(self, "add-apt-repository -y '{ppa_name}'"
                                 .format(ppa_name=ppa))

    def remove(self, ppa=None, repo_url=None):
        """
        This function used to remove ppa's
        If ppa is provided adds repo file to
            /etc/apt/sources.list.d/
        command.
        """
        if ppa:
            EEShellExec.cmd_exec(self, "add-apt-repository -y "
                                 "--remove '{ppa_name}'"
                                 .format(ppa_name=ppa))
        elif repo_url:
            repo_file_path = ("/etc/apt/sources.list.d/"
                              + EEVariables().ee_repo_file)

            try:
                repofile = open(repo_file_path, "w+")
                repofile.write(repofile.read().replace(repo_url, ""))
                repofile.close()
            except IOError as e:
                Log.debug(self, "{0}".format(e))
                Log.error(self, "File I/O error.")
            except Exception as e:
                Log.debug(self, "{0}".format(e))
                Log.error(self, "Unable to remove repo")

    def add_key(self, keyids, keyserver=None):
        """
        This function adds imports repository keys from keyserver.
        default keyserver is hkp://keys.gnupg.net
        user can provide other keyserver with keyserver="hkp://xyz"
        """
        EEShellExec.cmd_exec(self, "gpg --keyserver {serv}"
                             .format(serv=(keyserver or
                                           "hkp://keys.gnupg.net"))
                             + " --recv-keys {key}".format(key=keyids))
        EEShellExec.cmd_exec(self, "gpg -a --export --armor {0}"
                             .format(keyids)
                             + " | apt-key add - ")
