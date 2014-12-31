"""EasyEngine bootstrapping."""

# All built-in application controllers should be imported, and registered
# in this file in the same way as EEBaseController.

from cement.core import handler
from ee.cli.controllers.base import EEBaseController
from ee.cli.controllers.secure import EESecureController
from ee.cli.controllers.isl import EEImportslowlogController
from ee.cli.controllers.info import EEInfoController


def load(app):
    handler.register(EEBaseController)
    handler.register(EEInfoController)
    handler.register(EEImportslowlogController)
    handler.register(EESecureController)
