from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.logging import Log
from ee.core.variables import EEVariables
from ee.core.aptget import EEAptGet
from ee.core.apt_repo import EERepo
from ee.core.services import EEService
from ee.core.fileutils import EEFileUtils
import configparser
import os


class EEStackUpgradeController(CementBaseController):
    class Meta:
        label = 'upgrade'
        stacked_on = 'stack'
        stacked_type = 'nested'
        description = ('UPgrade stack safely')
        arguments = [
            (['--php56'],
                dict(help="Upgrade to PHP5.6 from PHP5.5",
                     action='store_true')),
            (['--no-prompt'],
                dict(help="Upgrade Packages without any prompt",
                     action='store_true')),

            ]

    @expose(hide=True)
    def upgrade_php56(self):
        pass

    @expose(hide=True)
    def default(self):
        # All package update
        if ((not self.app.pargs.php56)):
            # apt-get update
            EEAptGet.update(self)
            # display packges update
            EEAptGet.check_upgrade(self)
            Log.info(self, "During package update process non nginx-cached"
                     " parts of your site may remain down")
            # Check prompt
            if (not self.app.pargs.no_prompt):
                start_upgrade = input("Do you want to continue:[y/N]")
                if start_upgrade != "Y" and start_upgrade != "y":
                    Log.error(self, "Not starting package update")
            # Update packages
            Log.info(self, "Updating packages, please wait...")
            EEAptGet.dist_upgrade(self)
            Log.info(self, "Successfully updated packages")

        # PHP 5.6 to 5.6
        elif (self.app.pargs.php56):
            if not os.path.isfile("/etc/apt/sources.list.d/"
                                  "ondrej-php5-trusty.list"):
                Log.error("Unable to find PHP 5.5")
            Log.info(self, "During PHP update process non nginx-cached"
                     " parts of your site may remain down")

            # Check prompt
            if (not self.app.pargs.no_prompt):
                start_upgrade = input("Do you want to continue:[y/N]")
                if start_upgrade != "Y" and start_upgrade != "y":
                    Log.error(self, "Not starting PHP package update")
            EEFileUtils.remove(self, ['/etc/apt/sources.list.d/'
                                      'ondrej-php5-trusty.list'])
            EERepo.add(self, ppa=EEVariables.ee_php_repo)
            Log.info(self, "Updating apt-cache, please wait...")
            EEAptGet.update(self)
            Log.info(self, "Installing packages, please wait ...")
            EEAptGet.install(self, EEVariables.ee_php)
            Log.info(self, "Successfully updated from PHP 5.5 to PHP 5.6")
        else:
            self.app.args.print_help()
