from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.shellexec import EEShellExec
from ee.core.logging import Log
import os


def import_slow_log_plugin_hook(app):
    pass


class EEImportslowlogController(CementBaseController):
    class Meta:
        label = 'import_slow_log'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = 'Import MySQL slow log to Anemometer database'

    @expose(hide=True)
    def default(self):
        if os.path.isdir("/var/www/22222/htdocs/db/anemometer"):
            if os.path.isfile("/var/log/mysql/mysql-slow.log"):
                # Get Anemometer user name and password
                host = os.popen("grep -e \"\'host\'\" /var/www/22222/htdocs/"
                                "db/anemometer/conf/config.inc.php  "
                                "| head -1 | cut -d\\\' -f4 | "
                                "tr -d '\n'").read()
                user = os.popen("grep -e \"\'user\'\" /var/www/22222/htdocs/"
                                "db/anemometer/conf/config.inc.php  "
                                "| head -1 | cut -d\\\' -f4 | "
                                "tr -d '\n'").read()
                password = os.popen("grep -e \"\'password\'\" /var/www/22222/"
                                    "htdocs/db/anemometer/conf/config.inc.php "
                                    "| head -1 | cut -d\\\' -f4 | "
                                    "tr -d '\n'").read()
                # Import slow log Anemometer using pt-query-digest
                EEShellExec.cmd_exec(self, "pt-query-digest --user={0} "
                                     "--password={1} "
                                     "--review D=slow_query_log,"
                                     "t=global_query_review "
                                     "--history D=slow_query_log,t="
                                     "global_query_review_history "
                                     "--no-report --limit=0% "
                                     "--filter=\" \\$event->{{Bytes}} = "
                                     "length(\\$event->{{arg}}) "
                                     "and \\$event->{{hostname}}=\\\""
                                     "{2}\\\"\" "
                                     "/var/log/mysql/mysql-slow.log"
                                     .format(user, password, host))
            else:
                Log.error(self, "Unable to find MySQL slow log file")
        else:
            Log.error(self, "Anemometer is not installed")


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EEImportslowlogController)

    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', import_slow_log_plugin_hook)
