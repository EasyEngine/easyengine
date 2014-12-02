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
        # TODO Default action for ee site command
        print("Inside EESiteController.default().")

    @expose(hide=True)
    def create(self):
        # TODO Write code for ee site create command here
        print("Inside EESiteController.create().")

    @expose(hide=True)
    def delete(self):
        # TODO Write code for ee site delete command here
        print("Inside EESiteController.delete().")

    @expose(hide=True)
    def enable(self):
        # TODO Write code for ee site enable command here
        print("Inside EESiteController.enable().")

    @expose(hide=True)
    def disable(self):
        # TODO Write code for ee site disable command here
        print("Inside EESiteController.disable().")

    @expose(hide=True)
    def info(self):
        # TODO Write code for ee site info command here
        print("Inside EESiteController.info().")

    @expose(hide=True)
    def log(self):
        # TODO Write code for ee site log command here
        print("Inside EESiteController.log().")

    @expose(hide=True)
    def edit(self):
        # TODO Write code for ee site edit command here
        print("Inside EESiteController.edit().")

    @expose(hide=True)
    def show(self):
        # TODO Write code for ee site edit command here
        print("Inside EESiteController.show().")

    @expose(hide=True)
    def list(self):
        # TODO Write code for ee site list command here
        print("Inside EESiteController.list().")

    @expose(hide=True)
    def cd(self):
        # TODO Write code for ee site cd here
        print("Inside EESiteController.cd().")

    @expose(hide=True)
    def update(self):
        # TODO Write code for ee site update here
        print("Inside EESiteController.update().")

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
