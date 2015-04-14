"""EasyEngine main application entry point."""
import sys
import os

# this has to happen after you import sys, but before you import anything
# from Cement "source: https://github.com/datafolklabs/cement/issues/290"
if '--debug' in sys.argv:
    sys.argv.remove('--debug')
    TOGGLE_DEBUG = True
else:
    TOGGLE_DEBUG = False

from cement.core import foundation
from cement.utils.misc import init_defaults
from cement.core.exc import FrameworkError, CaughtSignal
from cement.ext.ext_argparse import ArgParseArgumentHandler
from ee.core import exc
from ee.cli.ext.ee_outputhandler import EEOutputHandler

# Application default.  Should update config/ee.conf to reflect any
# changes, or additions here.
defaults = init_defaults('ee')

# All internal/external plugin configurations are loaded from here
defaults['ee']['plugin_config_dir'] = '/etc/ee/plugins.d'

# External plugins (generally, do not ship with application code)
defaults['ee']['plugin_dir'] = '/var/lib/ee/plugins'

# External templates (generally, do not ship with application code)
defaults['ee']['template_dir'] = '/var/lib/ee/templates'


class EEArgHandler(ArgParseArgumentHandler):
    class Meta:
        label = 'ee_args_handler'

    def error(self, message):
        super(EEArgHandler, self).error("unknown args")


class EEApp(foundation.CementApp):
    class Meta:
        label = 'ee'

        config_defaults = defaults

        # All built-in application bootstrapping (always run)
        bootstrap = 'ee.cli.bootstrap'

        # Optional plugin bootstrapping (only run if plugin is enabled)
        plugin_bootstrap = 'ee.cli.plugins'

        # Internal templates (ship with application code)
        template_module = 'ee.cli.templates'

        # Internal plugins (ship with application code)
        plugin_bootstrap = 'ee.cli.plugins'

        extensions = ['mustache']

        # default output handler
        output_handler = EEOutputHandler

        arg_handler = EEArgHandler

        debug = TOGGLE_DEBUG


class EETestApp(EEApp):
    """A test app that is better suited for testing."""
    class Meta:
        argv = []
        config_files = []


# Define the applicaiton object outside of main, as some libraries might wish
# to import it as a global (rather than passing it into another class/func)
app = EEApp()


def main():
    try:
        global sys
        # Default our exit status to 0 (non-error)
        code = 0

        # if not root...kick out
        if not os.geteuid() == 0:
            print("\nOnly root or sudo user can run this EasyEngine\n")
            app.close(1)

        # Setup the application
        app.setup()

        # Dump all arguments into ee log
        app.log.debug(sys.argv)

        # Run the application
        app.run()
    except exc.EEError as e:
        # Catch our application errors and exit 1 (error)
        code = 1
        print(e)
    except FrameworkError as e:
        # Catch framework errors and exit 1 (error)
        code = 1
        print(e)
    except CaughtSignal as e:
        # Default Cement signals are SIGINT and SIGTERM, exit 0 (non-error)
        code = 0
        print(e)
    except Exception as e:
        code = 1
        print(e)
    finally:
        # Print an exception (if it occurred) and --debug was passed
        if app.debug:
            import sys
            import traceback

            exc_type, exc_value, exc_traceback = sys.exc_info()
            if exc_traceback is not None:
                traceback.print_exc()

        # # Close the application
    app.close(code)


def get_test_app(**kw):
    app = EEApp(**kw)
    return app

if __name__ == '__main__':
    main()
