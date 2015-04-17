from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.shellexec import EEShellExec
from ee.core.logging import Log
from ee.core.variables import EEVariables
import os


def ee_import_slow_log_hook(app):
    pass


class EEImportslowlogController(CementBaseController):
    class Meta:
        label = 'import_slow_log'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = 'Import MySQL slow log to Anemometer database'
        usage = "ee import-slow-log"

    @expose(hide=True)
    def default(self):
        Log.info(self, "This command is deprecated."
                 " You can use this command instead, " +
                 Log.ENDC + Log.BOLD + "\n`ee debug --import-slow-log`" +
                 Log.ENDC)


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EEImportslowlogController)

    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', ee_import_slow_log_hook)
