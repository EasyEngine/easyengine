from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook
from ee.core.download import EEDownload
from ee.core.logging import Log
import time
import os


def ee_update_hook(app):
    # do something with the ``app`` object here.
    pass


class EEUpdateController(CementBaseController):
    class Meta:
        label = 'ee_update'
        stacked_on = 'base'
        aliases = ['update']
        aliases_only = True
        stacked_type = 'nested'
        description = ('update EasyEngine to latest version')
        usage = "ee update"

    @expose(hide=True)
    def default(self):
        filename = "eeupdate" + time.strftime("%Y%m%d-%H%M%S")
        EEDownload.download(self, [["http://rt.cx/eeup",
                                    "/tmp/{0}".format(filename),
                                    "update script"]])
        try:
            Log.info(self, "updating EasyEngine, please wait...")
            os.system("bash /tmp/{0}".format(filename))
        except OSError as e:
            Log.debug(self, str(e))
            Log.error(self, "EasyEngine update failed !")
        except Exception as e:
            Log.debug(self, str(e))
            Log.error(self, "EasyEngine update failed !")


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EEUpdateController)
    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', ee_update_hook)
