"""EasyEngine base controller."""

from cement.core.controller import CementBaseController, expose
from ee.core.variables import EEVariables
VERSION = EEVariables.ee_version

BANNER = """
EasyEngine v%s
Copyright (c) 2016 rtCamp Solutions Pvt. Ltd.
""" % VERSION


class EEBaseController(CementBaseController):
    class Meta:
        label = 'base'
        description = ("EasyEngine is the commandline tool to manage your"
                       " websites based on WordPress and Nginx with easy to"
                       " use commands")
        arguments = [
            (['-v', '--version'], dict(action='version', version=BANNER)),
            ]

    @expose(hide=True)
    def default(self):
        self.app.args.print_help()
