from cement.core.controller import CementBaseController, expose

class EESecureController(CementBaseController):
    class Meta:
        label = 'secure'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = 'secure command used for debugging issued with stack or site specific configuration'
        arguments = [
            (['--ip'],
            dict(help='whitelist ip addresses to access admin tools without authentication',
            action='store_true') ),
            (['--auth'],
            dict(help='change http auth credentials', action='store_true') ),
            (['--port'],
            dict(help='change port for admin tools default is 22222', action='store_true') ),
            ]

    @expose(hide=True)
    def default(self):
        # TODO Default action for ee debug command
        print ("Inside EESecureController.default().")
