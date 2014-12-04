from cement.core.controller import CementBaseController, expose

class EEImportslowlogController(CementBaseController):
    class Meta:
        label = 'import_slow_log'
        stacked_on = 'base'
        stacked_type = 'nested'
        description = 'info command used for debugging issued with stack or site specific configuration'
        arguments = [
            (['--mysql'],
            dict(help='get mysql configuration information', action='store_true') ),
            (['--php'],
            dict(help='get php configuration information', action='store_true') ),
            (['--nginx'],
            dict(help='get nginx configuration information', action='store_true') ),
            ]

    @expose(hide=True)
    def default(self):
        # TODO Default action for ee debug command
        print ("Inside EEImportslowlogController.default().")
