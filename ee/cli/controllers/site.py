"""EasyEngine site controller."""

from cement.core.controller import CementBaseController, expose
from ee.core.dummy import EEDummy

class EESiteController(CementBaseController):
    class Meta:
        label = 'site'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = 'site command manages website configuration with the help of the following subcommands'
        arguments = [
            (['-f', '--foo'],
             dict(help='the notorious foo option', dest='foo', action='store',
                  metavar='TEXT') ),
            ]

    @expose(hide=True)
    def default(self):
        # Default action for ee site command
        print("Inside EESiteController.default().")

    def create(self):
        # Write code for ee site create command here
        print("Inside EESiteController.create().")

    def delete(self):
        # Write code for ee site delete command here
        print("Inside EESiteController.delete().")

    def enable(self):
        # Write code for ee site enable command here
        print("Inside EESiteController.enable().")

    def disable(self):
        # Write code for ee site disable command here
        print("Inside EESiteController.disable().")

    def info(self):
        # Write code for ee site info command here
        print("Inside EESiteController.info().")

    def log(self):
        # Write code for ee site log command here
        print("Inside EESiteController.log().")

    def edit(self):
        # Write code for ee site edit command here
        print("Inside EESiteController.edit().")

    def show(self):
        # Write code for ee site edit command here
        print("Inside EESiteController.show().")

    def list(self):
        # Write code for ee site list command here
        print("Inside EESiteController.list().")

        # site command Options and subcommand calls and definations to
        # mention here

        # If using an output handler such as 'mustache', you could also
        # render a data dictionary using a template.  For example:
        #
        #   data = dict(foo='bar')
        #   self.app.render(data, 'default.mustache')
        #
        #
        # The 'default.mustache' file would be loaded from
        # ``ee.cli.templates``, or ``/var/lib/ee/templates/``.
        #
