"""CLI tests for ee."""

from ee.utils import test

class CliTestCase(test.EETestCase):
    def test_ee_cli(self):
        self.app.setup()
        self.app.run()
        self.app.close()
