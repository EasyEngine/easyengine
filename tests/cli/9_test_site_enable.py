from ee.utils import test
from ee.cli.main import get_test_app


class CliTestCaseSite(test.EETestCase):

    def test_ee_cli(self):
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_enable(self):
        self.app = get_test_app(argv=['site', 'enable', 'example2.com'])
        self.app.setup()
        self.app.run()
        self.app.close()
