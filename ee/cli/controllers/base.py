"""EasyEngine base controller."""

from cement.core.controller import CementBaseController, expose


class EEBaseController(CementBaseController):
    class Meta:
        label = 'base'
        description = ("EasyEngine is the commandline tool to manage your"
                       " websites based on WordPress and Nginx with easy to"
                       " use commands")

    @expose(hide=True)
    def default(self):
        self.app.args.print_help()
