from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.logging import Log
from ee.core.variables import EEVariables
from ee.core.aptget import EEAptGet
from ee.core.apt_repo import EERepo
from ee.core.services import EEService
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
        if ((not self.app.pargs.php56)):
            # apt-get update
            EEAptGet.update(self)
            # display packges update
            EEAptGet.check_upgrade(self)
            Log.info(self, "During package update process non nginx-cached"
                     " parts of your site may remain down")
            # Check prompt
            if (self.app.pargs.no_prompt):
                start_upgrade = input("Do you want to continue:[y/N]")
                if start_upgrade != "Y" and start_upgrade != "y":
                    Log.error(self, "Not starting package update")
            # Update packages
            Log.info("Updating packages, please wait...")
            EEAptGet.dist_upgrade(self)
