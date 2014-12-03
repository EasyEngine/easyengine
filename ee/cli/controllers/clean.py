"""EasyEngine site controller."""

from cement.core.controller import CementBaseController, expose

class EECleanController(CementBaseController):
    class Meta:
        label = 'clean'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = 'clean command cleans different cache with following options'
        arguments = [
            (['--all'],
            dict(help='clean all cache', action='store_true') ),
            (['--fastcgi'],
            dict(help='clean fastcgi cache', action='store_true')),
            (['--memcache'],
            dict(help='clean memcache', action='store_true')),
            (['--opcache'],
            dict(help='clean opcode cache cache', action='store_true'))
            ]

    @expose(hide=True)
    def default(self):
        # TODO Default action for ee clean command here
        print ("Inside EECleanController.default().")

        # clean command Options and subcommand calls and definations to
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
