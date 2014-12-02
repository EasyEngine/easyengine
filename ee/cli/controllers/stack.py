"""EasyEngine site controller."""

from cement.core.controller import CementBaseController, expose

class EEStackController(CementBaseController):
    class Meta:
        label = 'stack'
        interface = controller.IController
        stacked_on = 'base'
        stacked_type = 'nested'
        description = 'stack command manages stack operations'
            (['-f', '--foo'],
             dict(help='the notorious foo option', dest='foo', action='store',
                  metavar='TEXT') ),
            ]

    @expose(hide=True)
    def default(self):
        # Default action for ee stack command
        print ("Inside EEStackController.default().")

        # stack command Options and subcommand calls and definations to
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
