"""EasyEngine bootstrapping."""

# All built-in application controllers should be imported, and registered
# in this file in the same way as EEBaseController.

from cement.core import handler
from ee.cli.controllers.base import EEBaseController
from ee.cli.controllers.site import EESiteController

def load(app):
    handler.register(EEBaseController)
    handler.register(EESiteController)
