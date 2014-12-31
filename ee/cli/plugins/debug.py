"""Debug Plugin for EasyEngine."""

from cement.core.controller import CementBaseController, expose
from cement.core import handler, hook


def debug_plugin_hook(app):
    # do something with the ``app`` object here.
    pass


class EEDebugController(CementBaseController):
    class Meta:
        label = 'debug'
        description = 'debug command enables/disbaled stack debug'
        stacked_on = 'base'
        stacked_type = 'nested'
        arguments = [
            (['--stop'],
                dict(help='Install web stack', action='store_true')),
            (['--start'],
                dict(help='Install admin tools stack', action='store_true')),
            (['--nginx'],
                dict(help='Install mail server stack', action='store_true')),
            (['--php'],
                dict(help='Install Nginx stack', action='store_true')),
            (['--fpm'],
                dict(help='Install PHP stack', action='store_true')),
            (['--mysql'],
                dict(help='Install MySQL stack', action='store_true')),
            (['--wp'],
                dict(help='Install Postfix stack', action='store_true')),
            (['--rewrite'],
                dict(help='Install WPCLI stack', action='store_true')),
            (['-i', '--interactive'],
                dict(help='Install WPCLI stack', action='store_true')),
            ]

    @expose(hide=True)
    def default(self):
        print("Inside Debug")


def load(app):
    # register the plugin class.. this only happens if the plugin is enabled
    handler.register(EEDebugController)
    # register a hook (function) to run after arguments are parsed.
    hook.register('post_argument_parsing', debug_plugin_hook)
