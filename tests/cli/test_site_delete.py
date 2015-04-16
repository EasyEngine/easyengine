from ee.utils import test
from ee.cli.main import get_test_app


class CliTestCaseSite(test.EETestCase):

    def test_ee_cli(self):
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_detele(self):
        self.app = get_test_app(argv=['site', 'delete', 'example1.com',
                                      '--no-prompt'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_detele_all(self):
        self.app = get_test_app(argv=['site', 'delete', 'example2.com',
                                      '--all', '--no-prompt'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_detele_db(self):
        self.app = get_test_app(argv=['site', 'delete', 'example3.com',
                                      '--db', '--no-prompt'])
        self.app.setup()
        self.app.run()
        self.app.close()

    def test_ee_cli_site_detele_files(self):
        self.app = get_test_app(argv=['site', 'delete', 'example4.com',
                                      '--files', '--no-prompt'])
        self.app.setup()
        self.app.run()
        self.app.close()
