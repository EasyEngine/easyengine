from ee.utils import test
from ee.cli.main import get_test_app


class CliTestCaseSite(test.EETestCase):

    def test_ee_cli(self):
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_show_edit(self):
        self.app = get_test_app(argv=['site', 'show', 'example1.com'])
        self.app.setup()
        self.app.run()
        self.app.close()
