"""Testing utilities for EasyEngine."""
from ee.cli.main import EETestApp
from cement.utils.test import *


class EETestCase(CementTestCase):
    app_class = EETestApp

    def setUp(self):
        """Override setup actions (for every test)."""
        super(EETestCase, self).setUp()

    def tearDown(self):
        """Override teardown actions (for every test)."""
        super(EETestCase, self).tearDown()
