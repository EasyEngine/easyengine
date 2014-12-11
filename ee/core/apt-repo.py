
from ee.core.variables import EEVariables


class EERepo():
    """Manage Repositories"""

    def __init__(self):
        """Initialize """
        pass

    def add(self, repo_url=None, codename=None, repo_type=None, ppa=None):
        # TODO add repository code
        repo_file_path = ("/etc/apt/sources.list.d/"
                          + EEVariables().ee_repo_file)
        try:
            with open(repo_file_path, "a") as repofile:
                repofile.write("\n" + repo_url + " " + codename +
                               " " + repo_type)
                repofile.close()
        except Exception as e:
            raise

    def remove(self, repo_url=None, codename=None, repo_type=None, ppa=None):
        # TODO remove repository
        pass

# if __name__ == '__main__':
#   EERepo().add(repo_url="http://ds.asf", codename="trusty", repo_type="main")
