"""EasyEngine site controller."""

from cement.core.controller import CementBaseController, expose

class EEDebugController(CementBaseController):
    class Meta:
        label = 'debug'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = 'debug command used for debugging issued with stack or site specific configuration'
        arguments = [
            (['--fpm'],
            dict(help='debug fpm', action='store_true') ),
            (['--mysql'],
            dict(help='debug mysql', action='store_true') ),
            (['--nginx'],
            dict(help='debug nginx', action='store_true') ),
            (['--php'],
            dict(help='debug php', action='store_true') ),
            (['--rewrite'],
            dict(help='debug rewrite', action='store_true') ),
            (['--stop'],
            dict(help='stop debugging', action='store_true') ),
            ]

    @expose(hide=True)
    def default(self):
        # TODO Default action for ee debug command
        print ("Inside EEDebugController.default().")

        # debug command Options and subcommand calls and definations to
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
